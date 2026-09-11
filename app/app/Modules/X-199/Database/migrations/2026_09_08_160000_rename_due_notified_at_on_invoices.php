<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('due_notified_at', 'due_detected_at');
        });

        DB::statement('ALTER INDEX IF EXISTS invoices_due_notified_at_index RENAME TO invoices_due_detected_at_index');
    }

    public function down(): void
    {
        DB::statement('ALTER INDEX IF EXISTS invoices_due_detected_at_index RENAME TO invoices_due_notified_at_index');

        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('due_detected_at', 'due_notified_at');
        });
    }
};
