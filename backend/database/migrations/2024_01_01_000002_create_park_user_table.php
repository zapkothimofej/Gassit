<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('park_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('park_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'park_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('park_user');
    }
};
