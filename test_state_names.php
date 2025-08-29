<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\State;
use App\Models\StudentAcademic;
use App\Models\StudentContact;
use App\Models\StudentMedical;
use App\Models\Department;

echo "Testing State Model and Relationships...\n\n";

// Check if states table has data
$statesCount = State::count();
echo "States in database: $statesCount\n";

if ($statesCount > 0) {
    $sampleStates = State::take(3)->get();
    echo "Sample states:\n";
    foreach ($sampleStates as $state) {
        echo "- ID: {$state->id}, Name: {$state->name}, Country: {$state->country_id}\n";
    }
} else {
    echo "No states found. Creating sample states...\n";
    $sampleStates = [
        ['name' => 'Lagos', 'country_id' => 'NG'],
        ['name' => 'Ogun', 'country_id' => 'NG'],
        ['name' => 'FCT', 'country_id' => 'NG'],
    ];
    
    foreach ($sampleStates as $stateData) {
        State::create($stateData);
        echo "Created state: {$stateData['name']}\n";
    }
}

echo "\n";

// Find test student
$student = Student::where('username', 'vug/csc/16/1336')->first();

if ($student) {
    echo "Test student found: {$student->fname} {$student->lname}\n";
    echo "Current state_id: {$student->state_id}\n";
    echo "Current state_of_origin: {$student->state_of_origin ?? 'null'}\n";
    
    // Update student with valid state IDs if they don't exist
    if (!$student->state_id || !$student->state_of_origin) {
        $firstState = State::first();
        if ($firstState) {
            $student->update([
                'state_id' => $firstState->id,
                'state_of_origin' => $firstState->id
            ]);
            echo "Updated student with state ID: {$firstState->id} ({$firstState->name})\n";
        }
    }
    
    // Test the relationships
    $student = $student->fresh()->load(['stateOfOrigin', 'state']);
    
    echo "\nTesting relationships:\n";
    echo "State (current): " . ($student->state ? $student->state->name : 'Not found') . "\n";
    echo "State of Origin: " . ($student->stateOfOrigin ? $student->stateOfOrigin->name : 'Not found') . "\n";
    
    // Simulate the controller logic
    $academic = StudentAcademic::where('student_id', $student->id)->first();
    $contact = StudentContact::where('student_id', $student->id)->first();
    $medical = StudentMedical::where('student_id', $student->id)->first();
    $department = null;
    
    if ($academic && $academic->department_id) {
        $department = Department::find($academic->department_id);
    }
    
    $handleNull = function($value) {
        return $value ?? 'Not provided';
    };
    
    $response = [
        'student' => [
            'id' => $student->id,
            'fname' => $handleNull($student->fname),
            'lname' => $handleNull($student->lname),
            'state' => $handleNull($student->state->name ?? null),
            'state_of_origin' => $handleNull($student->stateOfOrigin->name ?? null),
        ]
    ];
    
    echo "\nSimulated API Response (student data only):\n";
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    
} else {
    echo "Test student not found!\n";
}

echo "\nTest completed.\n";