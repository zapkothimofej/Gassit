<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            ['key' => 'invoice_day', 'value' => '1', 'description' => 'Day of month invoices are generated'],
            ['key' => 'dunning_delay_1', 'value' => '7', 'description' => 'Days after due date for first dunning'],
            ['key' => 'dunning_delay_2', 'value' => '14', 'description' => 'Days after due date for second dunning'],
            ['key' => 'dunning_delay_3', 'value' => '30', 'description' => 'Days after due date for third dunning'],
            ['key' => 'payment_retry_max', 'value' => '3', 'description' => 'Maximum payment retry attempts'],
            ['key' => 'session_timeout_minutes', 'value' => '60', 'description' => 'Session timeout in minutes'],
            ['key' => 'login_max_attempts', 'value' => '5', 'description' => 'Maximum failed login attempts'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
