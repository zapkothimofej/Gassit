<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('park_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', [
                'pending',
                'received',
                'held',
                'returned',
                'partially_returned',
                'forfeited',
            ])->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->decimal('return_amount', 10, 2)->nullable();
            $table->decimal('deduction_amount', 10, 2)->nullable();
            $table->text('deduction_reason')->nullable();
            $table->enum('return_method', ['bank_transfer', 'mollie_payout'])->nullable();
            $table->string('mollie_payment_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
