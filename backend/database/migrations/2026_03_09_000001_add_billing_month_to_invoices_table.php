<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_month', 7)->nullable()->after('invoice_number')->comment('Format: Y-m');
            $table->unique(['contract_id', 'billing_month'], 'invoices_contract_billing_month_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_contract_billing_month_unique');
            $table->dropColumn('billing_month');
        });
    }
};
