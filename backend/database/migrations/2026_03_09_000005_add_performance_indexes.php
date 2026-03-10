<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->hasIndex('customers', 'customers_email_index')) {
                $table->index('email');
            }
            if (!$this->hasIndex('customers', 'customers_status_index')) {
                $table->index('status');
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (!$this->hasIndex('units', 'units_park_id_status_index')) {
                $table->index(['park_id', 'status']);
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!$this->hasIndex('invoices', 'invoices_customer_id_status_due_date_index')) {
                $table->index(['customer_id', 'status', 'due_date']);
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (!$this->hasIndex('applications', 'applications_park_id_status_assigned_to_index')) {
                $table->index(['park_id', 'status', 'assigned_to']);
            }
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (!$this->hasIndex('contracts', 'contracts_customer_id_status_index')) {
                $table->index(['customer_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndexIfExists('customers_email_index');
            $table->dropIndexIfExists('customers_status_index');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndexIfExists('units_park_id_status_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndexIfExists('invoices_customer_id_status_due_date_index');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndexIfExists('applications_park_id_status_assigned_to_index');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndexIfExists('contracts_customer_id_status_index');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        return count($indexes) > 0;
    }
};
