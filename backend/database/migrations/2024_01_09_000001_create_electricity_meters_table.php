<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electricity_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('meter_number');
            $table->enum('meter_type', ['main', 'sub']);
            $table->boolean('active')->default(true);
            $table->date('installed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electricity_meters');
    }
};
