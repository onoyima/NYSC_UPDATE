<?php

namespace App\Http\Controllers;

use App\Models\Studentnysc;
use App\Models\NyscPayment;
use App\Models\NyscTempSubmission;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Hash;

class NyscAdminController extends Controller
{
    /**
     * Get dashboard data for NYSC admin
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(): \Illuminate\Http\JsonResponse
    {
        // Get all submitted student data
        $students = Studentnysc::where('is_submitted', true)
            ->with(['student', 'payments' => function($query) {
                $query->where('status', 'successful');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get temp submissions count
        $tempSubmissions = \App\Models\NyscTempSubmission::where('status', 'pending')->count();

        // Basic statistics
        $totalStudents = $students->count();
        $totalPaid = $students->where('is_paid', true)->count();
        $totalUnpaid = $totalStudents - $totalPaid;

        // Department breakdown
        $departmentStats = $students->groupBy('department')
            ->map(function ($group, $department) use ($totalStudents) {
                $count = $group->count();
                return [
                    'department' => $department ?: 'Unknown',
                    'count' => $count,
                    'percentage' => $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0
                ];
            })->values();

        // Gender breakdown
        $genderStats = $students->groupBy('gender')
            ->map(function ($group, $gender) use ($totalStudents) {
                $count = $group->count();
                return [
                    'gender' => ucfirst($gender ?: 'Unknown'),
                    'count' => $count,
                    'percentage' => $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0
                ];
            })->values();

        // Payment analytics
        $allPayments = \App\Models\NyscPayment::where('status', 'successful')->get();
        $totalRevenue = $allPayments->sum('amount');
        $averageAmount = $allPayments->count() > 0 ? $allPayments->avg('amount') : 0;
        $successRate = $totalStudents > 0 ? round(($totalPaid / $totalStudents) * 100, 1) : 0;

        // Monthly payment trends (last 7 months)
        $monthlyTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthPayments = $allPayments->filter(function($payment) use ($date) {
                return $payment->payment_date &&
                       $payment->payment_date->format('Y-m') === $date->format('Y-m');
            });

            $monthlyTrends[] = [
                'month' => $date->format('M'),
                'revenue' => $monthPayments->sum('amount'),
                'count' => $monthPayments->count()
            ];
        }

        // Recent registrations (last 10)
        $recentRegistrations = $students->take(10)->map(function($student) {
            return [
                'id' => $student->student_id,
                'name' => trim(($student->fname ?? '') . ' ' . ($student->lname ?? '')),
                'matric_no' => $student->matric_no,
                'department' => $student->department,
                'is_paid' => $student->is_paid,
                'created_at' => $student->created_at
            ];
        });

        return response()->json([
            'totalStudents' => $totalStudents,
            'confirmedData' => $totalStudents,
            'completedPayments' => $totalPaid,
            'pendingPayments' => $totalUnpaid,
            'totalNyscSubmissions' => $totalStudents,
            'totalTempSubmissions' => $tempSubmissions,
            'recentRegistrations' => $recentRegistrations,
            'departmentBreakdown' => $departmentStats,
            'genderBreakdown' => $genderStats,
            'paymentAnalytics' => [
                'totalRevenue' => $totalRevenue,
                'averageAmount' => round($averageAmount, 2),
                'successRate' => $successRate,
                'monthlyTrends' => $monthlyTrends
            ],
            'system_status' => $this->getSystemStatus(),
        ]);
    }

    /**
     * Get system control settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getControl(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'system_status' => $this->getSystemStatus(),
        ]);
    }

    /**
     * Control the update window
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function control(Request $request)
    {
        $request->validate([
            'open' => 'required|boolean',
            'deadline' => 'required|date',
        ]);

        // Store settings in cache
        Cache::put('nysc.system_open', $request->open, now()->addYears(1));
        Cache::put('nysc.payment_deadline', $request->deadline, now()->addYears(1));

        return response()->json([
            'message' => 'System settings updated successfully.',
            'system_status' => $this->getSystemStatus(),
        ]);
    }

    /**
     * Update a student's NYSC record
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $studentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStudent(Request $request, $studentId): \Illuminate\Http\JsonResponse
    {
        $nysc = Studentnysc::where('student_id', $studentId)->first();

        if (!$nysc) {
            return response()->json([
                'message' => 'Student record not found.',
            ], 404);
        }

        // Validate the request
        $validated = $request->validate([
            'fname' => 'sometimes|string|max:100',
            'lname' => 'sometimes|string|max:100',
            'mname' => 'sometimes|string|max:100',
            'gender' => 'sometimes|in:male,female,other',
            'dob' => 'sometimes|date',
            'marital_status' => 'sometimes|in:single,married,divorced,widowed',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'address' => 'sometimes|string',
            'state_of_origin' => 'sometimes|string|max:100',
            'lga' => 'sometimes|string|max:100',
            'matric_no' => 'sometimes|string|max:50',
            'course_of_study' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'faculty' => 'sometimes|string|max:255',
            'graduation_year' => 'sometimes|string|size:4',
            'cgpa' => 'sometimes|numeric|min:0|max:5',
            'jambno' => 'sometimes|string|max:20',
            'study_mode' => 'sometimes|string|max:50',
            'emergency_contact_name' => 'sometimes|string|max:255',
            'emergency_contact_phone' => 'sometimes|string|max:20',
            'emergency_contact_relationship' => 'sometimes|string|max:100',
            'emergency_contact_address' => 'sometimes|string',
            'is_paid' => 'sometimes|boolean',
            'payment_amount' => 'sometimes|integer',
        ]);

        // Update the record
        $nysc->update($validated);

        return response()->json([
            'message' => 'Student record updated successfully.',
            'data' => $nysc,
        ]);
    }

    /**
     * Export student data
     *
     * @param  string  $format
     * @return \Illuminate\Http\Response
     */
    public function export($format)
    {
        $students = Studentnysc::where('is_submitted', true)
            ->with(['student', 'payments' => function($query) {
                $query->where('status', 'successful')->orderBy('payment_date', 'desc');
            }])
            ->get();

        // Transform data to include payment information
        $students = $students->map(function($student) {
            $latestPayment = $student->payments->first();
            $student->payment_amount = $latestPayment ? $latestPayment->amount : 0;
            $student->payment_date = $latestPayment ? $latestPayment->payment_date : null;
            return $student;
        });

        switch ($format) {
            case 'excel':
                return $this->exportExcel($students);
            case 'csv':
                return $this->exportCsv($students);
            case 'pdf':
                return $this->exportPdf($students);
            default:
                return response()->json([
                    'message' => 'Invalid export format.',
                ], 400);
        }
    }

    /**
     * Get payment data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function payments(): \Illuminate\Http\JsonResponse
    {
        // Get all payment records from nysc_payment table with student information
        $payments = \App\Models\NyscPayment::with(['studentNysc.student'])
            ->orderBy('payment_date', 'desc')
            ->get()
            ->map(function($payment) {
                $studentNysc = $payment->studentNysc;
                $student = $studentNysc ? $studentNysc->student : null;

                return [
                    'id' => $payment->id,
                    'student_id' => $payment->student_id,
                    'student_name' => $studentNysc ? trim($studentNysc->fname . ' ' . $studentNysc->mname . ' ' . $studentNysc->lname) : 'N/A',
                    'matric_number' => $studentNysc ? $studentNysc->matric_no : 'N/A',
                    'email' => $student ? $student->email : 'N/A',
                    'department' => $studentNysc ? $studentNysc->department : 'N/A',
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method ?? 'paystack',
                    'payment_status' => $payment->status,
                    'transaction_reference' => $payment->payment_reference,
                    'payment_date' => $payment->payment_date,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                ];
            });

        // Calculate statistics from actual payment records
        $allPayments = \App\Models\NyscPayment::where('status', 'successful')->get();
        $totalAmount = $allPayments->sum('amount');
        $standardFeeCount = $allPayments->where('amount', 500)->count();
        $lateFeeCount = $allPayments->where('amount', 10000)->count();

        return response()->json([
            'payments' => $payments,
            'total' => $payments->count(),
            'statistics' => [
                'total_amount' => $totalAmount,
                'standard_fee_count' => $standardFeeCount,
                'late_fee_count' => $lateFeeCount,
            ],
        ]);
    }

    /**
     * Get system status
     *
     * @return array
     */
    private function getSystemStatus()
    {
        $isOpen = Cache::get('nysc.system_open', true);
        $deadline = Cache::get('nysc.payment_deadline', now()->addDays(30));

        return [
            'is_open' => $isOpen,
            'deadline' => $deadline,
            'is_late_fee' => now()->gt($deadline),
            'current_fee' => now()->gt($deadline) ? 10000 : 500,
        ];
    }

    /**
     * Export data as Excel
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $students
     * @return \Illuminate\Http\Response
     */
    private function exportExcel($students)
    {
        // In a real application, you would use a library like PhpSpreadsheet or Laravel Excel
        // For this example, we'll just return a JSON response
        return response()->json([
            'message' => 'Excel export functionality would be implemented here.',
            'data' => $students,
        ]);
    }

    /**
     * Export data as CSV
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $students
     * @return \Illuminate\Http\Response
     */
    private function exportCsv($students)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="nysc-students.csv"',
        ];

        $callback = function() use ($students) {
            $file = fopen('php://output', 'w');

            // Add headers
            fputcsv($file, [
                'ID', 'First Name', 'Middle Name', 'Last Name', 'Matric No', 'JAMB No', 'Study Mode', 'Gender', 'Date of Birth',
                'Marital Status', 'Phone', 'Email', 'Address', 'State of Origin',
                'LGA', 'Course of Study', 'Department', 'Faculty', 'Graduation Year',
                'CGPA', 'Payment Status', 'Payment Amount', 'Payment Date'
            ]);

            // Add data
            foreach ($students as $student) {
                fputcsv($file, [
                    $student->id,
                    $student->fname,
                    $student->mname,
                    $student->lname,
                    $student->matric_no,
                    $student->jambno,
                    $student->study_mode,
                    $student->gender,
                    $student->dob,
                    $student->marital_status,
                    $student->phone,
                    $student->email,
                    $student->address,
                    $student->state_of_origin,
                    $student->lga,
                    $student->course_of_study,
                    $student->department,
                    $student->faculty,
                    $student->graduation_year,
                    $student->cgpa,
                    $student->is_paid ? 'Paid' : 'Unpaid',
                    $student->payment_amount,
                    $student->payment_date,
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export data as PDF
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $students
     * @return \Illuminate\Http\Response
     */
    private function exportPdf($students)
    {
        // In a real application, you would use a library like DOMPDF or TCPDF
        // For this example, we'll just return a JSON response
        return response()->json([
            'message' => 'PDF export functionality would be implemented here.',
            'data' => $students,
        ]);
    }

    /**
     * Get students list with pagination and filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudents(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Studentnysc::where('is_submitted', true)->with('student');

        // Apply search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fname', 'like', "%{$search}%")
                  ->orWhere('lname', 'like', "%{$search}%")
                  ->orWhere('matric_no', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply payment status filter
        if ($request->has('payment_status') && $request->payment_status !== 'all') {
            $query->where('is_paid', $request->payment_status === 'paid');
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $students = $query->paginate($perPage);

        return response()->json($students);
    }

    /**
     * Get system settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSystemSettings(): \Illuminate\Http\JsonResponse
    {
        $settings = [
            'registration_fee' => Cache::get('nysc.registration_fee', 500),
            'late_fee' => Cache::get('nysc.late_fee', 10000),
            'payment_deadline' => Cache::get('nysc.payment_deadline', now()->addDays(30)),
            'system_open' => Cache::get('nysc.system_open', true),
            'system_message' => Cache::get('nysc.system_message', ''),
            'contact_email' => Cache::get('nysc.contact_email', 'admin@nysc.gov.ng'),
            'contact_phone' => Cache::get('nysc.contact_phone', '+234-800-NYSC'),
        ];

        return response()->json(['settings' => $settings]);
    }

    /**
     * Update system settings
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSystemSettings(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'registration_fee' => 'sometimes|integer|min:0',
            'late_fee' => 'sometimes|integer|min:0',
            'payment_deadline' => 'sometimes|date',
            'system_open' => 'sometimes|boolean',
            'system_message' => 'sometimes|string|max:1000',
            'contact_email' => 'sometimes|email',
            'contact_phone' => 'sometimes|string|max:20',
        ]);

        // Store settings in cache
        foreach ($validated as $key => $value) {
            Cache::put('nysc.' . $key, $value, now()->addYears(1));
        }

        return response()->json([
            'message' => 'System settings updated successfully.',
            'settings' => $validated,
        ]);
    }

    /**
     * Get email settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmailSettings(): \Illuminate\Http\JsonResponse
    {
        $settings = [
            'smtp_host' => Cache::get('nysc.smtp_host', ''),
            'smtp_port' => Cache::get('nysc.smtp_port', 587),
            'smtp_username' => Cache::get('nysc.smtp_username', ''),
            'smtp_encryption' => Cache::get('nysc.smtp_encryption', 'tls'),
            'from_email' => Cache::get('nysc.from_email', ''),
            'from_name' => Cache::get('nysc.from_name', 'NYSC Portal'),
        ];

        return response()->json(['settings' => $settings]);
    }

    /**
     * Update email settings
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEmailSettings(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'smtp_host' => 'sometimes|string|max:255',
            'smtp_port' => 'sometimes|integer|min:1|max:65535',
            'smtp_username' => 'sometimes|string|max:255',
            'smtp_password' => 'sometimes|string|max:255',
            'smtp_encryption' => 'sometimes|in:tls,ssl,none',
            'from_email' => 'sometimes|email',
            'from_name' => 'sometimes|string|max:255',
        ]);

        // Store settings in cache (except password for security)
        foreach ($validated as $key => $value) {
            if ($key !== 'smtp_password') {
                Cache::put('nysc.' . $key, $value, now()->addYears(1));
            }
        }

        return response()->json([
            'message' => 'Email settings updated successfully.',
        ]);
    }

    /**
     * Upload CSV file for bulk student import
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadCsv(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('csv_file');
            $path = $file->store('temp', 'local');
            $fullPath = storage_path('app/' . $path);

            // Read and process CSV
            $csvData = [];
            $errors = [];
            $successCount = 0;
            $errorCount = 0;

            if (($handle = fopen($fullPath, 'r')) !== FALSE) {
                $header = fgetcsv($handle); // Skip header row
                $rowNumber = 1;

                while (($data = fgetcsv($handle)) !== FALSE) {
                    $rowNumber++;

                    try {
                        // Map CSV columns to database fields
                        $studentData = [
                            'fname' => $data[0] ?? '',
                            'lname' => $data[1] ?? '',
                            'mname' => $data[2] ?? '',
                            'matric_no' => $data[3] ?? '',
                            'email' => $data[4] ?? '',
                            'phone' => $data[5] ?? '',
                            'gender' => $data[6] ?? '',
                            'dob' => $data[7] ?? null,
                            'state_of_origin' => $data[8] ?? '',
                            'lga' => $data[9] ?? '',
                            'course_of_study' => $data[10] ?? '',
                            'department' => $data[11] ?? '',
                            'graduation_year' => $data[12] ?? null,
                            'cgpa' => $data[13] ?? null,
                            'jambno' => $data[14] ?? '',
                            'study_mode' => $data[15] ?? 'full-time',
                        ];

                        // Validate required fields
                        if (empty($studentData['fname']) || empty($studentData['lname']) || empty($studentData['matric_no'])) {
                            $errors[] = "Row {$rowNumber}: Missing required fields (fname, lname, matric_no)";
                            $errorCount++;
                            continue;
                        }

                        // Check if student already exists
                        $existingStudent = \App\Models\Student::where('matric_no', $studentData['matric_no'])->first();
                        if ($existingStudent) {
                            // Update existing student
                            $existingStudent->update($studentData);
                        } else {
                            // Create new student
                            \App\Models\Student::create($studentData);
                        }

                        $successCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                        $errorCount++;
                    }
                }
                fclose($handle);
            }

            // Clean up temp file
            unlink($fullPath);

            return response()->json([
                'success' => true,
                'message' => "CSV import completed. {$successCount} records processed successfully.",
                'statistics' => [
                    'total_rows' => $successCount + $errorCount,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => array_slice($errors, 0, 10) // Limit to first 10 errors
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process CSV file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download CSV template for student import
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadCsvTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_import_template.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');

            // Add headers
            fputcsv($file, [
                'First Name', 'Last Name', 'Middle Name', 'Matric Number', 'Email', 'Phone',
                'Gender', 'Date of Birth (YYYY-MM-DD)', 'State of Origin', 'LGA',
                'Course of Study', 'Department', 'Graduation Year', 'CGPA', 'JAMB Number', 'Study Mode'
            ]);

            // Add sample data
            fputcsv($file, [
                'John', 'Doe', 'Smith', 'VUG/CSC/16/1001', 'john.doe@example.com', '08012345678',
                'Male', '1995-05-15', 'Lagos', 'Ikeja', 'Computer Science', 'Computer Science',
                '2020', '3.50', 'JAM123456789', 'full-time'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clear application cache
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache(): \Illuminate\Http\JsonResponse
    {
        try {
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test email configuration
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testEmail(): \Illuminate\Http\JsonResponse
    {
        try {
            // Send test email
            $testEmail = Cache::get('nysc.contact_email', 'onoyimab@veritas.edu.ng');

            // Here you would implement actual email sending logic
            // For now, we'll just return a success response

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $testEmail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    /**
     * Get all students with their NYSC data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStudents(): \Illuminate\Http\JsonResponse
    {
        try {
            $students = Studentnysc::with(['student', 'payments' => function($query) {
                $query->where('status', 'successful');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($nysc) {
                return [
                    'id' => $nysc->id,
                    'student_id' => $nysc->student_id,
                    'student_name' => trim(($nysc->fname ?? '') . ' ' . ($nysc->lname ?? '')),
                    'matric_number' => $nysc->matric_no ?? 'N/A',
                    'email' => $nysc->email ?? 'N/A',
                    'department' => $nysc->department ?? 'N/A',
                    'course_of_study' => $nysc->course_of_study ?? 'N/A',
                    'graduation_year' => $nysc->graduation_year ?? 'N/A',
                    'cgpa' => $nysc->cgpa ?? 'N/A',
                    'gender' => $nysc->gender ?? 'N/A',
                    'phone' => $nysc->phone ?? 'N/A',
                    'state_of_origin' => $nysc->state_of_origin ?? 'N/A',
                    'lga' => $nysc->lga ?? 'N/A',
                    'is_paid' => $nysc->is_paid ?? false,
                    'payment_amount' => $nysc->payment_amount ?? 0,
                    'is_submitted' => $nysc->is_submitted ?? false,
                    'payment_reference' => $nysc->payment_reference ?? 'N/A',
                    'payment_date' => $nysc->payment_date ?? null,
                    'created_at' => $nysc->created_at,
                    'updated_at' => $nysc->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $students,
                'total' => $students->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch students data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed information for a specific student
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentDetails(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $student = Studentnysc::with(['student', 'payments' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->where('student_id', $id)
            ->orWhere('id', $id)
            ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            $studentDetails = [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'fname' => $student->fname,
                'lname' => $student->lname,
                'mname' => $student->mname,
                'email' => $student->email,
                'phone' => $student->phone,
                'matric_no' => $student->matric_no,
                'department' => $student->department,
                'course_of_study' => $student->course_of_study,
                'graduation_year' => $student->graduation_year,
                'cgpa' => $student->cgpa,
                'gender' => $student->gender,
                'date_of_birth' => $student->date_of_birth,
                'state_of_origin' => $student->state_of_origin,
                'lga' => $student->lga,
                'address' => $student->address,
                'next_of_kin_name' => $student->next_of_kin_name,
                'next_of_kin_phone' => $student->next_of_kin_phone,
                'next_of_kin_relationship' => $student->next_of_kin_relationship,
                'is_paid' => $student->is_paid,
                'payment_amount' => $student->payment_amount,
                'payment_reference' => $student->payment_reference,
                'payment_date' => $student->payment_date,
                'is_submitted' => $student->is_submitted,
                'created_at' => $student->created_at,
                'updated_at' => $student->updated_at,
                'payments' => $student->payments->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'reference' => $payment->reference,
                        'status' => $payment->status,
                        'payment_date' => $payment->payment_date,
                        'created_at' => $payment->created_at
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $studentDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get temporary submissions
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubmissions(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            // Get temporary submissions with student information
            $submissions = \App\Models\NyscTempSubmission::with(['student'])
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            $formattedSubmissions = $submissions->map(function($submission) {
                $student = $submission->student;
                $formData = json_decode($submission->form_data, true) ?? [];

                return [
                    'id' => $submission->id,
                    'student_id' => $submission->student_id,
                    'student_name' => $student ? $student->fname . ' ' . $student->lname : 'N/A',
                    'matric_number' => $student ? $student->matric_no : 'N/A',
                    'email' => $student ? $student->email : 'N/A',
                    'department' => $formData['department'] ?? 'N/A',
                    'faculty' => $formData['faculty'] ?? 'N/A',
                    'level' => $formData['level'] ?? 'N/A',
                    'submission_type' => 'initial',
                    'submission_status' => $submission->status,
                    'submitted_data' => $formData,
                    'submission_date' => $submission->created_at,
                    'reviewed_date' => $submission->reviewed_at,
                    'reviewed_by' => $submission->reviewed_by,
                    'review_notes' => $submission->review_notes,
                    'created_at' => $submission->created_at,
                    'updated_at' => $submission->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'submissions' => $formattedSubmissions,
                'total' => $submissions->total(),
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch submissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get submission details
     *
     * @param string $submissionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubmissionDetails(string $submissionId): \Illuminate\Http\JsonResponse
    {
        try {
            $submission = \App\Models\NyscTempSubmission::with(['student'])
                ->findOrFail($submissionId);

            $student = $submission->student;
            $formData = json_decode($submission->form_data, true) ?? [];

            $submissionDetails = [
                'id' => $submission->id,
                'student_id' => $submission->student_id,
                'student_name' => $student ? $student->fname . ' ' . $student->lname : 'N/A',
                'matric_number' => $student ? $student->matric_no : 'N/A',
                'email' => $student ? $student->email : 'N/A',
                'department' => $formData['department'] ?? 'N/A',
                'faculty' => $formData['faculty'] ?? 'N/A',
                'level' => $formData['level'] ?? 'N/A',
                'submission_type' => 'initial',
                'submission_status' => $submission->status,
                'submitted_data' => $formData,
                'submission_date' => $submission->created_at,
                'reviewed_date' => $submission->reviewed_at,
                'reviewed_by' => $submission->reviewed_by,
                'review_notes' => $submission->review_notes,
                'created_at' => $submission->created_at,
                'updated_at' => $submission->updated_at,
            ];

            return response()->json([
                'success' => true,
                'submission' => $submissionDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch submission details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update submission status
     *
     * @param \Illuminate\Http\Request $request
     * @param string $submissionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSubmissionStatus(Request $request, string $submissionId): \Illuminate\Http\JsonResponse
    {
        try {
            $submission = \App\Models\NyscTempSubmission::findOrFail($submissionId);

            $submission->update([
                'status' => $request->status,
                'review_notes' => $request->notes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Submission status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update submission status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create export job
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createExportJob(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $type = $request->input('type'); // student_nysc, payments, submissions
            $format = $request->input('format'); // csv, excel, pdf
            $filters = $request->input('filters', []);

            // Generate unique job ID
            $jobId = uniqid('export_', true);

            // Store job in cache with initial status
            $jobData = [
                'id' => $jobId,
                'type' => $type,
                'format' => $format,
                'filters' => $filters,
                'status' => 'processing',
                'progress' => 0,
                'created_at' => now()->toISOString(),
            ];

            Cache::put("export_job_{$jobId}", $jobData, 3600); // 1 hour

            // Track job keys
            $cacheKeys = Cache::get('export_job_keys', []);
            $cacheKeys[] = "export_job_{$jobId}";
            Cache::put('export_job_keys', $cacheKeys, 3600);

            // Process export based on type
            $data = $this->getExportData($type, $filters);
            $recordCount = count($data);

            // Generate file
            $fileName = $this->generateExportFile($data, $type, $format, $jobId);

            // Update job status
            $jobData['status'] = 'completed';
            $jobData['progress'] = 100;
            $jobData['record_count'] = $recordCount;
            $jobData['file_name'] = $fileName;
            $jobData['download_url'] = "/api/nysc/admin/export-jobs/{$jobId}/download";

            Cache::put("export_job_{$jobId}", $jobData, 3600);

            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Export job created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create export job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get export jobs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExportJobs(): \Illuminate\Http\JsonResponse
    {
        try {
            // Get all export jobs from cache
            $jobs = [];
            $cacheKeys = Cache::get('export_job_keys', []);

            foreach ($cacheKeys as $key) {
                $job = Cache::get($key);
                if ($job) {
                    $jobs[] = $job;
                }
            }

            // Sort by created_at desc
            usort($jobs, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return response()->json([
                'success' => true,
                'jobs' => $jobs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch export jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get export job status
     *
     * @param string $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExportJobStatus(string $jobId): \Illuminate\Http\JsonResponse
    {
        try {
            $job = Cache::get("export_job_{$jobId}");

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export job not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'job' => $job
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get export job status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download export file
     *
     * @param string $jobId
     * @return \Illuminate\Http\Response
     */
    public function downloadExportFile(string $jobId)
    {
        try {
            $job = Cache::get("export_job_{$jobId}");

            if (!$job || $job['status'] !== 'completed') {
                return response()->json(['error' => 'Export job not found or not completed'], 404);
            }

            $filePath = storage_path("app/exports/{$job['file_name']}");

            if (!file_exists($filePath)) {
                return response()->json(['error' => 'Export file not found'], 404);
            }

            return response()->download($filePath);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to download file'], 500);
        }
    }

    /**
     * Get export data based on type and filters
     *
     * @param string $type
     * @param array $filters
     * @return array
     */
    private function getExportData(string $type, array $filters): array
    {
        switch ($type) {
            case 'student_nysc':
                return $this->getStudentNyscExportData($filters);
            case 'payments':
                return $this->getPaymentsExportData($filters);
            case 'submissions':
                return $this->getSubmissionsExportData($filters);
            default:
                throw new \Exception('Invalid export type');
        }
    }

    /**
     * Get student NYSC data for export
     *
     * @param array $filters
     * @return array
     */
    private function getStudentNyscExportData(array $filters): array
    {
        $query = Studentnysc::with(['student', 'payments']);

        // Apply filters
        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (!empty($filters['state'])) {
            $query->where('state_of_origin', $filters['state']);
        }

        if (!empty($filters['paymentStatus'])) {
            if ($filters['paymentStatus'] === 'paid') {
                $query->where('is_paid', true);
            } else {
                $query->where('is_paid', false);
            }
        }

        if (!empty($filters['matricNumbers'])) {
            $query->whereIn('matric_no', $filters['matricNumbers']);
        }

        if (!empty($filters['dateRange']['start']) && !empty($filters['dateRange']['end'])) {
            $query->whereBetween('created_at', [$filters['dateRange']['start'], $filters['dateRange']['end']]);
        }

        return $query->get()->map(function($student) {
            $latestPayment = $student->payments->first();
            return [
                'ID' => $student->id,
                'Student ID' => $student->student_id,
                'First Name' => $student->fname,
                'Last Name' => $student->lname,
                'Middle Name' => $student->mname,
                'Matric Number' => $student->matric_no,
                'Email' => $student->student ? $student->student->email : '',
                'Phone' => $student->phone,
                'Department' => $student->department,
                'Faculty' => $student->faculty,
                'Level' => $student->level,
                'Gender' => $student->gender,
                'Date of Birth' => $student->dob,
                'State of Origin' => $student->state_of_origin,
                'LGA' => $student->lga,
                'Address' => $student->address,
                'Is Paid' => $student->is_paid ? 'Yes' : 'No',
                'Payment Amount' => $latestPayment ? $latestPayment->amount : 0,
                'Payment Date' => $latestPayment ? $latestPayment->payment_date : '',
                'Submission Date' => $student->created_at,
            ];
        })->toArray();
    }

    /**
     * Get payments data for export
     *
     * @param array $filters
     * @return array
     */
    private function getPaymentsExportData(array $filters): array
    {
        $query = NyscPayment::with(['studentNysc.student']);

        // Apply filters
        if (!empty($filters['department'])) {
            $query->whereHas('studentNysc', function($q) use ($filters) {
                $q->where('department', $filters['department']);
            });
        }

        if (!empty($filters['paymentStatus'])) {
            $query->where('status', $filters['paymentStatus']);
        }

        if (!empty($filters['dateRange']['start']) && !empty($filters['dateRange']['end'])) {
            $query->whereBetween('payment_date', [$filters['dateRange']['start'], $filters['dateRange']['end']]);
        }

        return $query->get()->map(function($payment) {
            $studentNysc = $payment->studentNysc;
            $student = $studentNysc ? $studentNysc->student : null;

            return [
                'Payment ID' => $payment->id,
                'Student ID' => $payment->student_id,
                'Student Name' => $studentNysc ? trim($studentNysc->fname . ' ' . $studentNysc->mname . ' ' . $studentNysc->lname) : 'N/A',
                'Matric Number' => $studentNysc ? $studentNysc->matric_no : 'N/A',
                'Email' => $student ? $student->email : 'N/A',
                'Department' => $studentNysc ? $studentNysc->department : 'N/A',
                'Amount' => $payment->amount,
                'Payment Method' => $payment->payment_method ?? 'paystack',
                'Status' => $payment->status,
                'Reference' => $payment->payment_reference,
                'Payment Date' => $payment->payment_date,
                'Created At' => $payment->created_at,
            ];
        })->toArray();
    }

    /**
     * Get submissions data for export
     *
     * @param array $filters
     * @return array
     */
    private function getSubmissionsExportData(array $filters): array
    {
        $query = NyscTempSubmission::with(['student']);

        // Apply filters
        if (!empty($filters['department'])) {
            $query->whereJsonContains('form_data->department', $filters['department']);
        }

        if (!empty($filters['dateRange']['start']) && !empty($filters['dateRange']['end'])) {
            $query->whereBetween('created_at', [$filters['dateRange']['start'], $filters['dateRange']['end']]);
        }

        return $query->get()->map(function($submission) {
            $student = $submission->student;
            $formData = json_decode($submission->form_data, true) ?? [];

            return [
                'Submission ID' => $submission->id,
                'Student ID' => $submission->student_id,
                'Student Name' => $student ? $student->fname . ' ' . $student->lname : 'N/A',
                'Matric Number' => $student ? $student->matric_no : 'N/A',
                'Email' => $student ? $student->email : 'N/A',
                'Department' => $formData['department'] ?? 'N/A',
                'Faculty' => $formData['faculty'] ?? 'N/A',
                'Level' => $formData['level'] ?? 'N/A',
                'Status' => $submission->status,
                'Submission Date' => $submission->created_at,
                'Reviewed Date' => $submission->reviewed_at,
                'Review Notes' => $submission->review_notes,
            ];
        })->toArray();
    }

    /**
     * Generate export file
     *
     * @param array $data
     * @param string $type
     * @param string $format
     * @param string $jobId
     * @return string
     */
    private function generateExportFile(array $data, string $type, string $format, string $jobId): string
    {
        $fileName = "{$type}_{$format}_{$jobId}.{$format}";
        $filePath = storage_path("app/exports/{$fileName}");

        // Ensure exports directory exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        switch ($format) {
            case 'csv':
                $this->generateCsvFile($data, $filePath);
                break;
            case 'excel':
                $this->generateExcelFile($data, $filePath);
                break;
            case 'pdf':
                $this->generatePdfFile($data, $filePath, $type);
                break;
            default:
                throw new \Exception('Unsupported export format');
        }

        return $fileName;
    }

    /**
     * Generate CSV file
     *
     * @param array $data
     * @param string $filePath
     */
    private function generateCsvFile(array $data, string $filePath): void
    {
        $file = fopen($filePath, 'w');

        if (!empty($data)) {
            // Write headers
            fputcsv($file, array_keys($data[0]));

            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }

        fclose($file);
    }

    /**
     * Generate Excel file (simplified - using CSV format for now)
     *
     * @param array $data
     * @param string $filePath
     */
    private function generateExcelFile(array $data, string $filePath): void
    {
        // For now, generate as CSV with .xlsx extension
        // In production, you would use a library like PhpSpreadsheet
        $this->generateCsvFile($data, $filePath);
    }

    /**
     * Generate PDF file (simplified)
     *
     * @param array $data
     * @param string $filePath
     * @param string $type
     */
    private function generatePdfFile(array $data, string $filePath, string $type): void
    {
        // For now, generate a simple HTML table and save as text
        // In production, you would use a library like TCPDF or DomPDF
        $html = "<h1>" . ucfirst(str_replace('_', ' ', $type)) . " Export</h1>";
        $html .= "<table border='1'>";

        if (!empty($data)) {
            // Headers
            $html .= "<tr>";
            foreach (array_keys($data[0]) as $header) {
                $html .= "<th>{$header}</th>";
            }
            $html .= "</tr>";

            // Data
            foreach ($data as $row) {
                $html .= "<tr>";
                foreach ($row as $cell) {
                    $html .= "<td>{$cell}</td>";
                }
                $html .= "</tr>";
            }
        }

        $html .= "</table>";
        file_put_contents($filePath, $html);
    }

    // Admin Users Management Methods
    public function getAdminUsers(Request $request)
    {
        try {
            $query = Staff::query();

            // Apply filters
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('fname', 'like', "%{$search}%")
                      ->orWhere('lname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            $adminUsers = $query->select([
                'id', 'fname', 'lname', 'email', 'status',
                'created_at', 'updated_at', 'last_login_at'
            ])->get();

            // Transform data to match frontend interface
            $transformedUsers = $adminUsers->map(function($user) {
                // Get role based on staff ID (using the same logic as frontend)
                $role = $this->getUserRole($user->id);

                return [
                    'id' => $user->id,
                    'staff_id' => (string)$user->id,
                    'name' => trim($user->fname . ' ' . $user->lname),
                    'email' => $user->email,
                    'role' => $role,
                    'permissions' => $this->getRolePermissions($role),
                    'status' => $user->status === 'active' ? 'active' : 'inactive',
                    'last_login' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'users' => $transformedUsers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admin users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createAdminUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'fname' => 'required|string|max:255',
                'lname' => 'required|string|max:255',
                'email' => 'required|email|unique:staff,email',
                'password' => 'required|string|min:6',
                'role' => 'required|in:super_admin,admin,sub_admin,manager',
                'status' => 'required|in:active,inactive'
            ]);

            $user = Staff::create([
                'fname' => $validated['fname'],
                'lname' => $validated['lname'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => $validated['status']
            ]);

            // Store role information in cache for now
            // In production, this should be stored in a proper roles table
            Cache::put("user_role_{$user->id}", $validated['role'], now()->addYears(1));

            return response()->json([
                'success' => true,
                'message' => 'Admin user created successfully',
                'user' => [
                    'id' => $user->id,
                    'staff_id' => (string)$user->id,
                    'name' => trim($user->fname . ' ' . $user->lname),
                    'email' => $user->email,
                    'role' => $validated['role'],
                    'permissions' => $this->getRolePermissions($validated['role']),
                    'status' => $user->status === 'active' ? 'active' : 'inactive',
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create admin user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAdminUser(Request $request, $userId)
    {
        try {
            $user = Staff::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            $validated = $request->validate([
                'fname' => 'sometimes|required|string|max:255',
                'lname' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:staff,email,' . $userId,
                'password' => 'nullable|string|min:6',
                'role' => 'sometimes|required|in:super_admin,admin,sub_admin,manager',
                'status' => 'sometimes|required|in:active,inactive'
            ]);

            // Update user fields
            if (isset($validated['fname'])) $user->fname = $validated['fname'];
            if (isset($validated['lname'])) $user->lname = $validated['lname'];
            if (isset($validated['email'])) $user->email = $validated['email'];
            if (isset($validated['status'])) $user->status = $validated['status'];

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            // Update role in cache if provided
            if (isset($validated['role'])) {
                Cache::put("user_role_{$user->id}", $validated['role'], now()->addYears(1));
            }

            $role = $validated['role'] ?? $this->getUserRole($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Admin user updated successfully',
                'user' => [
                    'id' => $user->id,
                    'staff_id' => (string)$user->id,
                    'name' => trim($user->fname . ' ' . $user->lname),
                    'email' => $user->email,
                    'role' => $role,
                    'permissions' => $this->getRolePermissions($role),
                    'status' => $user->status === 'active' ? 'active' : 'inactive',
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update admin user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAdminUser($userId)
    {
        try {
            $user = Staff::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            // Prevent deletion of super admin
            if ($user->id == 596) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete super admin user'
                ], 403);
            }

            $user->delete();

            // Remove role from cache
            Cache::forget("user_role_{$userId}");

            return response()->json([
                'success' => true,
                'message' => 'Admin user deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete admin user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for role management
    private function getUserRole($staffId)
    {
        // Check cache first
        $cachedRole = Cache::get("user_role_{$staffId}");
        if ($cachedRole) {
            return $cachedRole;
        }

        // Fallback to hardcoded logic (same as frontend)
        if ($staffId == 596) {
            return 'super_admin';
        }

        if ($staffId >= 500 && $staffId < 600) {
            return 'admin';
        } else if ($staffId >= 400 && $staffId < 500) {
            return 'sub_admin';
        } else {
            return 'manager';
        }
    }

    private function getRolePermissions($role)
    {
        $permissions = [
            'super_admin' => [
                'canViewStudentNysc' => true,
                'canEditStudentNysc' => true,
                'canAddStudentNysc' => true,
                'canDeleteStudentNysc' => true,
                'canViewPayments' => true,
                'canEditPayments' => true,
                'canViewTempSubmissions' => true,
                'canEditTempSubmissions' => true,
                'canDownloadData' => true,
                'canAssignRoles' => true,
                'canViewAnalytics' => true,
                'canManageSystem' => true,
            ],
            'admin' => [
                'canViewStudentNysc' => true,
                'canEditStudentNysc' => true,
                'canAddStudentNysc' => true,
                'canDeleteStudentNysc' => true,
                'canViewPayments' => true,
                'canEditPayments' => true,
                'canViewTempSubmissions' => true,
                'canEditTempSubmissions' => true,
                'canDownloadData' => true,
                'canAssignRoles' => false,
                'canViewAnalytics' => true,
                'canManageSystem' => true,
            ],
            'sub_admin' => [
                'canViewStudentNysc' => true,
                'canEditStudentNysc' => true,
                'canAddStudentNysc' => true,
                'canDeleteStudentNysc' => false,
                'canViewPayments' => true,
                'canEditPayments' => false,
                'canViewTempSubmissions' => true,
                'canEditTempSubmissions' => true,
                'canDownloadData' => true,
                'canAssignRoles' => false,
                'canViewAnalytics' => true,
                'canManageSystem' => false,
            ],
            'manager' => [
                'canViewStudentNysc' => true,
                'canEditStudentNysc' => false,
                'canAddStudentNysc' => false,
                'canDeleteStudentNysc' => false,
                'canViewPayments' => true,
                'canEditPayments' => false,
                'canViewTempSubmissions' => true,
                'canEditTempSubmissions' => false,
                'canDownloadData' => true,
                'canAssignRoles' => false,
                'canViewAnalytics' => true,
                'canManageSystem' => false,
            ],
        ];

        return $permissions[$role] ?? $permissions['manager'];
    }
}
