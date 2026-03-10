<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_type_id')->constrained()->cascadeOnDelete();
            $table->string('unit_number');
            $table->integer('floor')->nullable();
            $table->string('building')->nullable();
            $table->decimal('size_m2', 8, 2);
            $table->decimal('rent_override', 10, 2)->nullable();
            $table->decimal('deposit_override', 10, 2)->nullable();
            $table->enum('status', ['free', 'reserved', 'rented', 'maintenance', 'inactive'])->default('free');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
