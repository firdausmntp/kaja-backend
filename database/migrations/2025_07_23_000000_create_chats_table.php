<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('message')->nullable();
            $table->string('attachment_url')->nullable();
            $table->enum('attachment_type', ['image', 'document', 'audio'])->nullable();
            $table->enum('message_type', ['text', 'image', 'document', 'system'])->default('text');
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['transaction_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chats');
    }
};
