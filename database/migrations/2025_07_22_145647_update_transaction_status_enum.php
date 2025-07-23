<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop the old enum and create new one with more statuses
            $table->dropColumn('status');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', [
                'pending',     // Pesanan baru dibuat, menunggu pembayaran
                'paid',        // Sudah dibayar, menunggu konfirmasi penjual
                'confirmed',   // Dikonfirmasi penjual, sedang diproses
                'ready',       // Pesanan siap diambil/diantar
                'completed',   // Pesanan selesai
                'cancelled'    // Dibatalkan
            ])->default('pending')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', ['paid', 'unpaid', 'canceled'])->default('unpaid')->after('payment_method');
        });
    }
};
