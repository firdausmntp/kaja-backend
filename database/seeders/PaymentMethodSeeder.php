<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Cash',
                'description' => 'Pembayaran tunai',
                'is_active' => true,
            ],
            [
                'name' => 'Transfer Bank',
                'description' => 'Transfer melalui rekening bank',
                'is_active' => true,
            ],
            [
                'name' => 'E-Wallet',
                'description' => 'Pembayaran melalui dompet digital (OVO, GoPay, DANA, dll)',
                'is_active' => true,
            ],
            [
                'name' => 'QRIS',
                'description' => 'Pembayaran melalui QR Code Indonesia Standard',
                'is_active' => true,
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::create($method);
        }
    }
}
