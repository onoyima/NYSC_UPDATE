<?php

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== Paystack Configuration Test ===\n";
echo "PAYSTACK_PUBLIC_KEY: " . ($_ENV['PAYSTACK_PUBLIC_KEY'] ?? 'NOT SET') . "\n";
echo "PAYSTACK_SECRET_KEY: " . (isset($_ENV['PAYSTACK_SECRET_KEY']) ? (strlen($_ENV['PAYSTACK_SECRET_KEY']) > 0 ? 'SET (' . strlen($_ENV['PAYSTACK_SECRET_KEY']) . ' chars)' : 'EMPTY') : 'NOT SET') . "\n";
echo "PAYSTACK_PAYMENT_URL: " . ($_ENV['PAYSTACK_PAYMENT_URL'] ?? 'NOT SET') . "\n";

echo "\n=== Laravel Config Test ===\n";

// Test Laravel config access
try {
    // Bootstrap Laravel
    $app = require_once 'bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "Paystack Public Key from config: " . (config('services.paystack.public_key') ?: 'NOT SET') . "\n";
    echo "Paystack Secret Key from config: " . (config('services.paystack.secret_key') ? 'SET (' . strlen(config('services.paystack.secret_key')) . ' chars)' : 'NOT SET') . "\n";
    echo "Paystack Payment URL from config: " . (config('services.paystack.payment_url') ?: 'NOT SET') . "\n";
    
} catch (Exception $e) {
    echo "Error accessing Laravel config: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";