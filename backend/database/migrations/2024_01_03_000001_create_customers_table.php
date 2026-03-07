<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['private', 'company'])->default('private');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company_name')->nullable();
            $table->date('dob')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('zip');
            $table->string('country');
            $table->string('email');
            $table->string('phone');
            $table->string('id_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->enum('status', ['new', 'tenant', 'not_renting', 'debtor', 'troublemaker', 'blacklisted'])->default('new');
            $table->timestamp('gdpr_consent_at')->nullable();
            $table->timestamp('gdpr_deleted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
