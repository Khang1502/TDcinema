<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        // Legacy MyISAM tables ignore rollback. Tickets, invoices and voucher usage
        // must commit together so an unsuccessful booking does not consume a code.
        foreach (['promotions', 'tickets', 'payments', 'invoices', 'food_orders'] as $table) {
            if (Schema::hasTable($table)) DB::statement('ALTER TABLE `'.$table.'` ENGINE=InnoDB');
        }
    }

    public function down(): void
    {
        // Keep transaction support when rolling back application changes.
    }
};
