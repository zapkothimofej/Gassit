<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->enum('template_type', [
                'welcome',
                'contract',
                'invoice',
                'dunning_1',
                'dunning_2',
                'dunning_3',
                'termination',
                'deposit_return',
                'waiting_list',
                'custom',
            ]);
            $table->json('variables_json')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
