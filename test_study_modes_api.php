<?php

require_once 'vendor/autoload.php';

use App\Models\Student;
use App\Models\StudyMode;
use Illuminate\Support\Facades\DB;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Study Modes API Endpoint...\n\n";

try {
    // 1. Check if study modes exist in database
    echo "1. Checking StudyMode records in database:\n";
    $studyModes = StudyMode::where('status', 1)->get(['id', 'mode']);
    
    if ($studyModes->count() > 0) {
        foreach ($studyModes as $mode) {
            echo "   ID: {$mode->id}, Mode: '{$mode->mode}'\n";
        }
    } else {
        echo "   No active study modes found in database.\n";
    }
    
    echo "\n2. Testing API endpoint response format:\n";
    
    // Simulate the API response
    $response = [
        'study_modes' => $studyModes->toArray()
    ];
    
    echo "   API Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    
    echo "\n3. Testing if any student exists for authentication:\n";
    $student = Student::first();
    if ($student) {
        echo "   Found student: {$student->fname} {$student->lname} (ID: {$student->id})\n";
    } else {
        echo "   No students found in database.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";