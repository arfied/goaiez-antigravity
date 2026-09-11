<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every writer of these rows is firstOrCreate(), whose createOrFirst() catches a unique
 * violation inside a savepoint and returns the row that won. Without a unique index there
 * is nothing for that catch to catch, so two concurrent writers both insert. The index is
 * the whole fix: no writer changes. ar_dunning_actions keeps many rows per invoice, so its
 * index covers the escalation alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receivable_states')) {
            DB::statement(
                'CREATE UNIQUE INDEX receivable_states_business_invoice_unique
                 ON receivable_states (business_id, invoice_id)'
            );
        }

        if (Schema::hasTable('ar_dunning_actions')) {
            DB::statement(
                "CREATE UNIQUE INDEX ar_dunning_actions_one_escalation_unique
                 ON ar_dunning_actions (business_id, invoice_id)
                 WHERE action = 'escalate_to_human'"
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ar_dunning_actions_one_escalation_unique');
        DB::statement('DROP INDEX IF EXISTS receivable_states_business_invoice_unique');
    }
};
