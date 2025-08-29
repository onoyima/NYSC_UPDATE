<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\State;
use Illuminate\Support\Facades\DB;

echo "Testing State Relationship...\n\n";

// Check if states table has data
$stateCount = State::count();
echo "States in database: $stateCount\n";

if ($stateCount == 0) {
    echo "Creating sample states...\n";
    State::create(['name' => 'Lagos', 'country_id' => 1]);
    State::create(['name' => 'Abuja', 'country_id' => 1]);
    State::create(['name' => 'Kano', 'country_id' => 1]);
    echo "Sample states created.\n";
}

// Get a student and test the relationship
$student = Student::first();
if ($student) {
    echo "\nTesting student: {$student->fname} {$student->lname}\n";
    echo "Student state_id: {$student->state_id}\n";
    
    // Test the relationship
    $state = $student->state;
    if ($state) {
        echo "State name from relationship: {$state->name}\n";
    } else {
        echo "No state found for this student\n";
        
        // Assign a random state to test
        $randomState = State::inRandomOrder()->first();
        if ($randomState) {
            $student->state_id = $randomState->id;
            $student->save();
            echo "Assigned state '{$randomState->name}' to student\n";
            
            // Test again
            $student->refresh();
            $state = $student->state;
            echo "State name after assignment: {$state->name}\n";
        }
    }
    
    // Test the JSON response structure
    echo "\nTesting JSON response structure:\n";
    $student->load(['state']);
    $stateName = $student->state->name ?? 'Not provided';
    echo "State name for JSON: $stateName\n";
    
} else {
    echo "No students found in database\n";
}

echo "\nTest completed!\n";