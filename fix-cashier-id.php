<?php

/**
 * Script untuk mengupdate cashier_id pada transaksi yang sudah ada
 * Jalankan sekali saja via tinker atau buat sebagai artisan command
 */

use App\Models\Transaction;
use App\Models\TransactionItem;

// Cari semua transaksi yang cashier_id nya null
$transactions = Transaction::whereNull('cashier_id')->with('items.menu')->get();

echo "Found " . $transactions->count() . " transactions with null cashier_id\n";

foreach ($transactions as $transaction) {
    if ($transaction->items->isNotEmpty()) {
        // Ambil user_id dari menu pertama sebagai cashier_id
        $firstItem = $transaction->items->first();
        if ($firstItem && $firstItem->menu) {
            $merchantId = $firstItem->menu->user_id;

            // Validasi bahwa semua item dalam transaksi ini dari merchant yang sama
            $allFromSameMerchant = $transaction->items->every(function ($item) use ($merchantId) {
                return $item->menu && $item->menu->user_id === $merchantId;
            });

            if ($allFromSameMerchant) {
                $transaction->update(['cashier_id' => $merchantId]);
                echo "Updated transaction ID {$transaction->id} with cashier_id: {$merchantId}\n";
            } else {
                echo "Transaction ID {$transaction->id} has items from different merchants - SKIPPED\n";
            }
        } else {
            echo "Transaction ID {$transaction->id} has no menu data - SKIPPED\n";
        }
    } else {
        echo "Transaction ID {$transaction->id} has no items - SKIPPED\n";
    }
}

echo "Update completed!\n";
