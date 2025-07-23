<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    public function run()
    {
        // Get sample users and transaction
        $pembeli = User::where('role', 'pembeli')->first();
        $penjual = User::where('role', 'penjual')->first();
        $transaction = Transaction::first();

        if ($pembeli && $penjual && $transaction) {
            // Sample chat messages
            $chats = [
                [
                    'transaction_id' => $transaction->id,
                    'sender_id' => $pembeli->id,
                    'message' => 'Halo, kapan pesanan saya siap?',
                    'message_type' => 'text',
                    'created_at' => now()->subMinutes(30)
                ],
                [
                    'transaction_id' => $transaction->id,
                    'sender_id' => $penjual->id,
                    'message' => 'Halo! Pesanan Anda sedang diproses, estimasi 15 menit lagi.',
                    'message_type' => 'text',
                    'created_at' => now()->subMinutes(25)
                ],
                [
                    'transaction_id' => $transaction->id,
                    'sender_id' => $pembeli->id,
                    'message' => 'Baik, terima kasih! Bisa minta tanpa cabai ya?',
                    'message_type' => 'text',
                    'created_at' => now()->subMinutes(20)
                ],
                [
                    'transaction_id' => $transaction->id,
                    'sender_id' => $penjual->id,
                    'message' => 'Siap! Sudah kami catat. Pesanan tanpa cabai.',
                    'message_type' => 'text',
                    'created_at' => now()->subMinutes(15)
                ],
                [
                    'transaction_id' => $transaction->id,
                    'sender_id' => $penjual->id,
                    'message' => 'Pesanan Anda sudah siap! Silakan diambil di konter.',
                    'message_type' => 'text',
                    'created_at' => now()->subMinutes(5)
                ]
            ];

            foreach ($chats as $chat) {
                Chat::create($chat);
            }

            $this->command->info('Sample chat messages created successfully!');
        } else {
            $this->command->warn('Required users or transaction not found. Please run UserSeeder and TransactionSeeder first.');
        }
    }
}
