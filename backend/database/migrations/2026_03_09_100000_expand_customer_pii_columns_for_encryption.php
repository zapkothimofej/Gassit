<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('first_name')->change();
            $table->text('last_name')->change();
            $table->text('email')->change();
            $table->text('phone')->nullable()->change();
            $table->text('address')->change();
            $table->text('id_number')->nullable()->change();
            // dob stored as encrypted string (YYYY-MM-DD plaintext, then encrypted)
            $table->text('dob')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')->change();
            $table->string('last_name')->change();
            $table->string('email')->change();
            $table->string('phone')->nullable()->change();
            $table->string('address')->change();
            $table->string('id_number')->nullable()->change();
            $table->date('dob')->nullable()->change();
        });
    }
};
