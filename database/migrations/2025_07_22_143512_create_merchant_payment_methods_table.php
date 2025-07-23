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
        Schema::create('merchant_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // penjual
            $table->foreignId('payment_method_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->json('details'); // {account_number, account_name, bank_name, instructions, etc}
            $table->timestamps();

            // Unique constraint: satu penjual hanya bisa punya satu konfigurasi per payment method
            $table->unique(['user_id', 'payment_method_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_payment_methods');
    }
};
