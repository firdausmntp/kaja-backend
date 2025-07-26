<?php

// Test user relationships
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Transaction;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== User Relationships Test ===\n\n";

try {
    $user = User::find(6);

    if (!$user) {
        echo "User with ID 6 not found.\n";

        // List available users
        $users = User::select('id', 'name', 'role')->get();
        echo "Available users:\n";
        foreach ($users as $u) {
            echo "  ID {$u->id}: {$u->name} ({$u->role})\n";
        }
        exit;
    }

    echo "Testing user: {$user->name} (ID: {$user->id}, Role: {$user->role})\n\n";

    // Test transactions relation
    echo "Customer transactions: ";
    try {
        $customerTransactions = $user->transactions()->count();
        echo "{$customerTransactions}\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    // Test merchant transactions relation  
    echo "Merchant transactions: ";
    try {
        $merchantTransactions = $user->merchantTransactions()->count();
        echo "{$merchantTransactions}\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    // Test active transactions
    echo "\nActive customer transactions: ";
    try {
        $activeCustomer = $user->transactions()
            ->whereIn('status', ['pending', 'paid', 'confirmed', 'preparing', 'ready'])
            ->count();
        echo "{$activeCustomer}\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    echo "Active merchant transactions: ";
    try {
        $activeMerchant = $user->merchantTransactions()
            ->whereIn('status', ['pending', 'paid', 'confirmed', 'preparing', 'ready'])
            ->count();
        echo "{$activeMerchant}\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
