<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('provider');
            $table->decimal('monthly_premium', 10, 2);
            $table->decimal('coverage_amount', 10, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_options');
    }
};
