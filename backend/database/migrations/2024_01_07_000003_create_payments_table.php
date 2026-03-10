<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('payment_method', [
                'mollie',
                'bank_transfer',
                'cash',
                'sepa_direct_debit',
            ]);
            $table->string('mollie_payment_id')->nullable();
            $table->string('mollie_checkout_url')->nullable();
            $table->enum('status', [
                'pending',
                'paid',
                'failed',
                'refunded',
                'cancelled',
            ])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
