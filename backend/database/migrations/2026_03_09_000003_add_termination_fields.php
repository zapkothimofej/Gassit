<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->boolean('is_termination_inspection')->default(false)->after('contract_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('final_invoice_waived')->default(false)->after('terminated_at');
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropColumn('is_termination_inspection');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('final_invoice_waived');
        });
    }
};
