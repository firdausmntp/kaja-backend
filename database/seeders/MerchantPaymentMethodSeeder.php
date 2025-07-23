<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MerchantPaymentMethod;
use App\Models\User;
use App\Models\PaymentMethod;

class MerchantPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil penjual
        $penjual = User::where('role', 'penjual')->first();

        if (!$penjual) {
            return;
        }

        // Ambil payment methods
        $cash = PaymentMethod::where('name', 'Cash')->first();
        $bankTransfer = PaymentMethod::where('name', 'Bank Transfer')->first();
        $eWallet = PaymentMethod::where('name', 'E-Wallet')->first();

        // Setup payment methods untuk penjual
        if ($cash) {
            MerchantPaymentMethod::create([
                'user_id' => $penjual->id,
                'payment_method_id' => $cash->id,
                'is_active' => true,
                'details' => [
                    'instructions' => 'Pembayaran tunai langsung di kasir'
                ]
            ]);
        }

        if ($bankTransfer) {
            MerchantPaymentMethod::create([
                'user_id' => $penjual->id,
                'payment_method_id' => $bankTransfer->id,
                'is_active' => true,
                'details' => [
                    'bank_name' => 'Bank BCA',
                    'account_number' => '1234567890',
                    'account_name' => 'Warung Kaja',
                    'instructions' => 'Transfer ke rekening di atas dan upload bukti transfer'
                ]
            ]);
        }

        if ($eWallet) {
            MerchantPaymentMethod::create([
                'user_id' => $penjual->id,
                'payment_method_id' => $eWallet->id,
                'is_active' => true,
                'details' => [
                    'account_number' => '081234567890',
                    'account_name' => 'Warung Kaja',
                    'instructions' => 'Transfer ke nomor GoPay/OVO di atas dan upload bukti transfer'
                ]
            ]);
        }
    }
}
