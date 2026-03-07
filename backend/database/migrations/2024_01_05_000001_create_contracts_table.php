<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('notice_period_days')->default(30);
            $table->decimal('rent_amount', 10, 2);
            $table->decimal('deposit_amount', 10, 2);
            $table->decimal('insurance_amount', 10, 2)->default(0);
            $table->enum('status', [
                'draft',
                'awaiting_signature',
                'signed',
                'active',
                'terminated_by_customer',
                'terminated_by_lfg',
                'declined',
                'expired',
            ])->default('draft');
            $table->string('signed_pdf_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->unsignedBigInteger('termination_reason_id')->nullable();
            $table->date('termination_notice_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
