<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test the student details API endpoint
use App\Models\Student;
use App\Models\StudentAcademic;
use App\Models\StudentContact;
use App\Models\StudentMedical;
use App\Models\Department;
use App\Models\Studentnysc;

echo "Testing Student Details API Response Structure\n";
echo "================================================\n\n";

// Find test student
$student = Student::where('username', 'vug/csc/16/1336')->first();

if (!$student) {
    echo "Test student not found!\n";
    exit(1);
}

echo "Found student: {$student->fname} {$student->lname}\n";
echo "Student ID: {$student->id}\n\n";

// Get related data
$academic = StudentAcademic::where('student_id', $student->id)->first();
$contact = StudentContact::where('student_id', $student->id)->first();
$medical = StudentMedical::where('student_id', $student->id)->first();
$department = null;

if ($academic && $academic->department_id) {
    $department = Department::find($academic->department_id);
}

$nysc = Studentnysc::where('student_id', $student->id)->first();

// Helper function to handle null values
$handleNull = function($value) {
    return $value ?? 'Not provided';
};

// Build response structure (same as controller)
$response = [
    'student' => [
        'id' => $student->id,
        'title' => $handleNull($student->title_id),
        'fname' => $handleNull($student->fname),
        'mname' => $handleNull($student->mname),
        'lname' => $handleNull($student->lname),
        'gender' => $handleNull($student->gender),
        'dob' => $handleNull($student->dob),
        'country' => $handleNull($student->country_id),
        'state' => $handleNull($student->state_id),
        'lga' => $handleNull($student->lga_name),
        'city' => $handleNull($student->city),
        'religion' => $handleNull($student->religion),
        'marital_status' => $handleNull($student->marital_status),
        'address' => $handleNull($student->address),
        'phone' => $handleNull($student->phone),
        'email' => $handleNull($student->email),
        'state_of_origin' => $handleNull($student->state_id),
    ],
    'academic' => [
        'matric_no' => $handleNull($academic->matric_no ?? null),
        'course_study_id' => $handleNull($academic->course_study_id ?? null),
        'course_of_study' => $handleNull($academic->course_study_id ?? null),
        'department_id' => $handleNull($academic->department_id ?? null),
        'department' => $handleNull($department->name ?? null),
        'faculty_id' => $handleNull($academic->faculty_id ?? null),
        'level' => $handleNull($academic->level ?? null),
        'entry_session_id' => $handleNull($academic->entry_session_id ?? null),
        'academic_session_id' => $handleNull($academic->academic_session_id ?? null),
        'session' => $handleNull($academic->academic_session_id ?? null),
        'jamb_no' => $handleNull($academic->jamb_no ?? null),
        'jambno' => $handleNull($academic->jamb_no ?? null),
        'study_mode_id' => $handleNull($academic->study_mode_id ?? null),
        'study_mode' => $handleNull($academic->study_mode_id ?? null),
        'graduation_year' => $handleNull(null),
        'cgpa' => $handleNull(null),
    ],
    'contact' => [
        'title' => $handleNull($contact->title ?? null),
        'surname' => $handleNull($contact->surname ?? null),
        'other_names' => $handleNull($contact->other_names ?? null),
        'relationship' => $handleNull($contact->relationship ?? null),
        'address' => $handleNull($contact->address ?? null),
        'state' => $handleNull($contact->state ?? null),
        'city' => $handleNull($contact->city ?? null),
        'phone_no' => $handleNull($contact->phone_no ?? null),
        'phone_no_two' => $handleNull($contact->phone_no_two ?? null),
        'email' => $handleNull($contact->email ?? null),
        'emergency_contact_name' => $handleNull($contact->other_names ?? null),
        'emergency_contact_phone' => $handleNull($contact->phone_no ?? null),
        'emergency_contact_relationship' => $handleNull($contact->relationship ?? null),
        'emergency_contact_address' => $handleNull($contact->address ?? null),
    ],
    'medical' => [
        'physical' => $handleNull($medical->physical ?? null),
        'blood_group' => $handleNull($medical->blood_group ?? null),
        'condition' => $handleNull($medical->condition ?? null),
        'allergies' => $handleNull($medical->allergies ?? null),
        'genotype' => $handleNull($medical->genotype ?? null),
    ],
];

echo "API Response Structure:\n";
echo "======================\n";
echo json_encode($response, JSON_PRETTY_PRINT);

echo "\n\nData Sources Summary:\n";
echo "====================\n";
echo "Student record: " . ($student ? 'Found' : 'Not found') . "\n";
echo "Academic record: " . ($academic ? 'Found' : 'Not found') . "\n";
echo "Contact record: " . ($contact ? 'Found' : 'Not found') . "\n";
echo "Medical record: " . ($medical ? 'Found' : 'Not found') . "\n";
echo "Department record: " . ($department ? 'Found' : 'Not found') . "\n";
echo "NYSC record: " . ($nysc ? 'Found' : 'Not found') . "\n";

echo "\nTest completed successfully!\n";