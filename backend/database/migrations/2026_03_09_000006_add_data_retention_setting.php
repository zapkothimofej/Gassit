<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key'         => 'data_retention_years',
            'value'       => '10',
            'description' => 'GDPR data retention period in years (manual GDPR delete only, no automated deletion)',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'data_retention_years')->delete();
    }
};
