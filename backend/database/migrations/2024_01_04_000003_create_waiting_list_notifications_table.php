<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_list_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waiting_list_id')->constrained('waiting_list')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->enum('method', ['email', 'sms'])->default('email');
            $table->enum('response', ['interested', 'not_interested', 'no_response'])->default('no_response');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list_notifications');
    }
};
