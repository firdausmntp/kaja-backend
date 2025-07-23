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
            $table->text('notes')->nullable()->after('status'); // Catatan khusus dari pembeli
            $table->string('customer_name')->nullable()->after('notes'); // Nama untuk pesanan
            $table->string('customer_phone')->nullable()->after('customer_name'); // Nomor telepon
            $table->enum('order_type', ['dine_in', 'takeaway', 'delivery'])->default('takeaway')->after('customer_phone'); // Jenis pesanan
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['notes', 'customer_name', 'customer_phone', 'order_type']);
        });
    }
};
