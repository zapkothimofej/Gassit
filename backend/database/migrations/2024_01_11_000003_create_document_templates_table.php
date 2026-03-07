<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('park_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('document_type', [
                'rental_contract',
                'invoice',
                'termination_letter',
                'dunning_letter',
                'deposit_return',
                'welcome_letter',
            ]);
            $table->longText('template_html');
            $table->json('variables_json')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
