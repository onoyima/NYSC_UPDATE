<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudyMode;
use App\Models\StudentAcademic;

echo "Testing StudyMode Model and Relationship...\n\n";

echo "Checking if study_modes table exists...\n";
$tableExists = Schema::hasTable('study_modes');

// Test 1: Check if StudyMode table exists and has data
echo "1. Checking StudyMode records:\n";
try {
    $studyModes = StudyMode::all();
    if ($studyModes->count() > 0) {
        foreach ($studyModes as $mode) {
            echo "   ID: {$mode->id}, Mode: '{$mode->mode}', Status: {$mode->status}\n";
        }
    } else {
        echo "   study_modes table does not exist. Creating default records...\n";
        echo "   No StudyMode records found. Creating default records...\n";
        
        StudyMode::create(['id' => 1, 'mode' => 'Full-time', 'status' => 1]);
        StudyMode::create(['id' => 2, 'mode' => 'Part-time', 'status' => 1]);
        StudyMode::create(['id' => 3, 'mode' => 'Distance', 'status' => 1]);
        
        echo "   Default StudyMode records created.\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Test the relationship
echo "2. Testing StudentAcademic -> StudyMode relationship:\n";
try {
    $academic = StudentAcademic::with('studyMode')->first();
    if ($academic) {
        echo "   Student Academic ID: {$academic->id}\n";
        echo "   Study Mode ID: {$academic->study_mode_id}\n";
        if ($academic->studyMode) {
            echo "   Study Mode Name: '{$academic->studyMode->mode}'\n";
        } else {
            echo "   No StudyMode relationship found.\n";
        }
    } else {
        echo "   No StudentAcademic records found.\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";