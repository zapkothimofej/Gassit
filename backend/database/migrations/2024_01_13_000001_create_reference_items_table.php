<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_items', function (Blueprint $table) {
            $table->id();
            $table->enum('category', [
                'country',
                'city',
                'document_type',
                'termination_reason',
                'damage_category',
                'unit_feature',
                'industry_sector',
            ]);
            $table->string('value');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::table('reference_items')->insert([
            // German states
            ['category' => 'city', 'value' => 'berlin', 'label' => 'Berlin', 'sort_order' => 1],
            ['category' => 'city', 'value' => 'hamburg', 'label' => 'Hamburg', 'sort_order' => 2],
            ['category' => 'city', 'value' => 'munich', 'label' => 'München', 'sort_order' => 3],
            ['category' => 'city', 'value' => 'cologne', 'label' => 'Köln', 'sort_order' => 4],
            ['category' => 'city', 'value' => 'frankfurt', 'label' => 'Frankfurt am Main', 'sort_order' => 5],
            // Countries
            ['category' => 'country', 'value' => 'DE', 'label' => 'Deutschland', 'sort_order' => 1],
            ['category' => 'country', 'value' => 'AT', 'label' => 'Österreich', 'sort_order' => 2],
            ['category' => 'country', 'value' => 'CH', 'label' => 'Schweiz', 'sort_order' => 3],
            // Termination reasons
            ['category' => 'termination_reason', 'value' => 'end_of_contract', 'label' => 'Vertragsende', 'sort_order' => 1],
            ['category' => 'termination_reason', 'value' => 'non_payment', 'label' => 'Zahlungsverzug', 'sort_order' => 2],
            ['category' => 'termination_reason', 'value' => 'relocation', 'label' => 'Umzug', 'sort_order' => 3],
            ['category' => 'termination_reason', 'value' => 'no_longer_needed', 'label' => 'Nicht mehr benötigt', 'sort_order' => 4],
            ['category' => 'termination_reason', 'value' => 'damage', 'label' => 'Schäden', 'sort_order' => 5],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_items');
    }
};
