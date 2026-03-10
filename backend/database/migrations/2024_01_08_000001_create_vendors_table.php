<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('contact_name');
            $table->string('phone');
            $table->string('email');
            $table->string('specialty');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
