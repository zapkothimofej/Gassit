<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoice_items MODIFY COLUMN item_type ENUM('rent','deposit','insurance','electricity','damage','dunning_fee','discount','other','credit_note') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoice_items MODIFY COLUMN item_type ENUM('rent','deposit','insurance','electricity','damage','dunning_fee','discount','other') NOT NULL");
    }
};
