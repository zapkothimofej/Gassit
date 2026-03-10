<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electricity_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained('electricity_meters')->cascadeOnDelete();
            $table->date('reading_date');
            $table->decimal('reading_value', 12, 4);
            $table->string('photo_path')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->decimal('consumption', 12, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electricity_readings');
    }
};
