<?php

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configure Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Student;
use App\Models\StudentAcademic;
use App\Models\StudentContact;
use App\Models\StudentMedical;
use App\Models\Studentnysc;
use Illuminate\Support\Facades\DB;

echo "=== DEBUGGING FORM DATA FLOW ===\n\n";

// Get a test student (ID 1336 from previous tests)
$studentId = 1336;
$student = Student::find($studentId);

if (!$student) {
    echo "Student with ID $studentId not found.\n";
    exit(1);
}

echo "Testing with Student ID: $studentId\n";
echo "Student Name: {$student->fname} {$student->lname}\n\n";

// Get related data
$academic = StudentAcademic::where('student_id', $studentId)->first();
$contact = StudentContact::where('student_id', $studentId)->first();
$medical = StudentMedical::where('student_id', $studentId)->first();
$nysc = Studentnysc::where('student_id', $studentId)->first();

echo "=== 1. DATA FROM DATABASE TABLES ===\n";

echo "\n--- Student Table ---\n";
echo "fname: " . ($student->fname ?? 'NULL') . "\n";
echo "mname: " . ($student->mname ?? 'NULL') . "\n";
echo "lname: " . ($student->lname ?? 'NULL') . "\n";
echo "gender: " . ($student->gender ?? 'NULL') . "\n";
echo "dob: " . ($student->dob ?? 'NULL') . "\n";
echo "marital_status: " . ($student->marital_status ?? 'NULL') . "\n";
echo "phone: " . ($student->phone ?? 'NULL') . "\n";
echo "email: " . ($student->email ?? 'NULL') . "\n";
echo "address: " . ($student->address ?? 'NULL') . "\n";
echo "state_of_origin: " . ($student->state_of_origin ?? 'NULL') . "\n";
echo "lga_name: " . ($student->lga_name ?? 'NULL') . "\n";
echo "religion: " . ($student->religion ?? 'NULL') . "\n";

echo "\n--- Academic Table ---\n";
if ($academic) {
    echo "matric_no: " . ($academic->matric_no ?? 'NULL') . "\n";
    echo "course_study_id: " . ($academic->course_study_id ?? 'NULL') . "\n";
    echo "department_id: " . ($academic->department_id ?? 'NULL') . "\n";
    echo "faculty_id: " . ($academic->faculty_id ?? 'NULL') . "\n";
    echo "jamb_no: " . ($academic->jamb_no ?? 'NULL') . "\n";
    echo "study_mode_id: " . ($academic->study_mode_id ?? 'NULL') . "\n";
    echo "level: " . ($academic->level ?? 'NULL') . "\n";
    echo "academic_session_id: " . ($academic->academic_session_id ?? 'NULL') . "\n";
} else {
    echo "No academic record found\n";
}

echo "\n--- Contact Table ---\n";
if ($contact) {
    echo "title: " . ($contact->title ?? 'NULL') . "\n";
    echo "surname: " . ($contact->surname ?? 'NULL') . "\n";
    echo "other_names: " . ($contact->other_names ?? 'NULL') . "\n";
    echo "relationship: " . ($contact->relationship ?? 'NULL') . "\n";
    echo "address: " . ($contact->address ?? 'NULL') . "\n";
    echo "phone_no: " . ($contact->phone_no ?? 'NULL') . "\n";
    echo "email: " . ($contact->email ?? 'NULL') . "\n";
} else {
    echo "No contact record found\n";
}

echo "\n--- Medical Table ---\n";
if ($medical) {
    echo "blood_group: " . ($medical->blood_group ?? 'NULL') . "\n";
    echo "genotype: " . ($medical->genotype ?? 'NULL') . "\n";
    echo "physical: " . ($medical->physical ?? 'NULL') . "\n";
    echo "condition: " . ($medical->condition ?? 'NULL') . "\n";
    echo "allergies: " . ($medical->allergies ?? 'NULL') . "\n";
} else {
    echo "No medical record found\n";
}

echo "\n=== 2. SIMULATED FRONTEND DATA MAPPING ===\n";

// Simulate how the frontend maps data (from confirm page)
$getValue = function($value) {
    return ($value && $value !== 'Not provided') ? $value : '';
};

$prefilledData = [
    // Personal Information - from student table
    'fname' => $getValue($student->fname),
    'mname' => $getValue($student->mname),
    'lname' => $getValue($student->lname),
    'gender' => $getValue($student->gender),
    'dob' => $getValue($student->dob),
    'marital_status' => $getValue($student->marital_status),
    'religion' => $getValue($student->religion),
    'state_of_origin' => $getValue($student->state->name ?? null),
    
    // Contact Information - from student table
    'phone' => $getValue($student->phone),
    'email' => $getValue($student->email),
    'address' => $getValue($student->address),
    'lga' => $getValue($student->lga_name),
    
    // Academic Information - from academic table
    'matric_no' => $getValue($academic->matric_no ?? null),
    'course_of_study' => $getValue($academic->course_study_id ?? null),
    'department' => $getValue($academic->department_id ?? null),
    'faculty' => $getValue($academic->faculty_id ?? null),
    'jambno' => $getValue($academic->jamb_no ?? null),
    'study_mode' => $getValue($academic->study_mode_id ?? null),
    'level' => $getValue($academic->level ?? null),
    'session' => $getValue($academic->academic_session_id ?? null),
    'graduation_year' => '', // Not available in current schema
    'cgpa' => '', // Not available in current schema
    
    // Emergency Contact Information - from contact table
    'emergency_contact_title' => $getValue($contact->title ?? null),
    'emergency_contact_name' => function() use ($contact) {
        $surname = $contact->surname ?? '';
        $otherNames = $contact->other_names ?? '';
        return trim($surname . ' ' . $otherNames) ?: 'Not provided';
    },
    'emergency_contact_other_names' => $getValue($contact->other_names ?? null),
    'emergency_contact_relationship' => $getValue($contact->relationship ?? null),
    'emergency_contact_address' => $getValue($contact->address ?? null),
    'emergency_contact_phone' => $getValue($contact->phone_no ?? null),
    'emergency_contact_email' => $getValue($contact->email ?? null),
    
    // Medical Information - from medical table
    'blood_group' => $getValue($medical->blood_group ?? null),
    'genotype' => $getValue($medical->genotype ?? null),
    'physical_condition' => $getValue($medical->physical ?? null),
    'medical_condition' => $getValue($medical->condition ?? null),
    'allergies' => $getValue($medical->allergies ?? null)
];

// Execute the closure for emergency_contact_name
$prefilledData['emergency_contact_name'] = $prefilledData['emergency_contact_name']();

echo "\n--- Simulated localStorage Data (from confirmation form) ---\n";
foreach ($prefilledData as $key => $value) {
    echo "$key: " . ($value ?: 'EMPTY') . "\n";
}

echo "\n=== 3. SIMULATED PAYMENT PAGE MAPPING ===\n";

// Simulate how payment page maps the data
$mappedData = [
    // Personal Information
    'fname' => $prefilledData['fname'] ?: '',
    'lname' => $prefilledData['lname'] ?: '',
    'mname' => $prefilledData['mname'] ?: '',
    'gender' => strtolower($prefilledData['gender'] ?: ''),
    'dob' => $prefilledData['dob'] ?: '',
    'marital_status' => strtolower($prefilledData['marital_status'] ?: ''),
    'phone' => $prefilledData['phone'] ?: '',
    'email' => $prefilledData['email'] ?: '',
    'address' => $prefilledData['address'] ?: '',
    'state_of_origin' => $prefilledData['state_of_origin'] ?: '',
    'lga' => $prefilledData['lga'] ?: '',
    'religion' => $prefilledData['religion'] ?: '',
    
    // Academic Information
    'matric_no' => $prefilledData['matric_no'] ?: '',
    'course_of_study' => $prefilledData['course_of_study'] ?: '',
    'department' => $prefilledData['department'] ?: '',
    'faculty' => $prefilledData['faculty'] ?: '',
    'graduation_year' => $prefilledData['graduation_year'] ?: '',
    'cgpa' => $prefilledData['cgpa'] ?: '',
    'jambno' => $prefilledData['jambno'] ?: '',
    'study_mode' => $prefilledData['study_mode'] ?: '',
    
    // Emergency Contact Information
    'emergency_contact_name' => $prefilledData['emergency_contact_name'] ?: '',
    'emergency_contact_phone' => $prefilledData['emergency_contact_phone'] ?: '',
    'emergency_contact_relationship' => $prefilledData['emergency_contact_relationship'] ?: '',
    'emergency_contact_address' => $prefilledData['emergency_contact_address'] ?: '',
    
    // Medical Information (optional)
    'blood_group' => $prefilledData['blood_group'] ?: '',
    'genotype' => $prefilledData['genotype'] ?: '',
    'medical_condition' => $prefilledData['medical_condition'] ?: ''
];

echo "\n--- Mapped Data (sent to backend) ---\n";
foreach ($mappedData as $key => $value) {
    echo "$key: " . ($value ?: 'EMPTY') . "\n";
}

echo "\n=== 4. BACKEND VALIDATION CHECK ===\n";

// Check which required fields are missing
$requiredFields = [
    'fname', 'lname', 'gender', 'dob', 'marital_status',
    'phone', 'email', 'address', 'state_of_origin',
    'matric_no', 'course_of_study', 'department', 'faculty',
    'jambno', 'study_mode',
    'emergency_contact_name', 'emergency_contact_phone',
    'emergency_contact_relationship', 'emergency_contact_address'
];

$missingFields = [];
foreach ($requiredFields as $field) {
    if (empty($mappedData[$field])) {
        $missingFields[] = $field;
    }
}

if (empty($missingFields)) {
    echo "✅ All required fields are present\n";
} else {
    echo "❌ Missing required fields:\n";
    foreach ($missingFields as $field) {
        echo "  - $field\n";
    }
}

echo "\n=== 5. CURRENT NYSC RECORD STATE ===\n";

if ($nysc) {
    echo "NYSC Record exists:\n";
    echo "  is_paid: " . ($nysc->is_paid ? 'Yes' : 'No') . "\n";
    echo "  is_submitted: " . ($nysc->is_submitted ? 'Yes' : 'No') . "\n";
    echo "  fname: " . ($nysc->fname ?? 'NULL') . "\n";
    echo "  lname: " . ($nysc->lname ?? 'NULL') . "\n";
    echo "  gender: " . ($nysc->gender ?? 'NULL') . "\n";
    echo "  dob: " . ($nysc->dob ?? 'NULL') . "\n";
    echo "  matric_no: " . ($nysc->matric_no ?? 'NULL') . "\n";
    echo "  emergency_contact_name: " . ($nysc->emergency_contact_name ?? 'NULL') . "\n";
    echo "  emergency_contact_phone: " . ($nysc->emergency_contact_phone ?? 'NULL') . "\n";
} else {
    echo "No NYSC record found\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";