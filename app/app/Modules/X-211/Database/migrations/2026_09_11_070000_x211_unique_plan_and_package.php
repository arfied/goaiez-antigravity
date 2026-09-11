<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An invoice carries one payment plan and is packaged for collections once. ArEngine guards both
 * with a read before its insert, which refuses a second press but not two concurrent ones: both
 * read nothing and both insert. These indexes make the second insert fail, and ArEngine turns that
 * failure into the same refusal its guard gives.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_plans')) {
            DB::statement(
                'CREATE UNIQUE INDEX payment_plans_business_invoice_unique
                 ON payment_plans (business_id, invoice_id)'
            );
        }

        if (Schema::hasTable('ar_collections_packages')) {
            DB::statement(
                'CREATE UNIQUE INDEX ar_collections_packages_business_invoice_unique
                 ON ar_collections_packages (business_id, invoice_id)'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ar_collections_packages_business_invoice_unique');
        DB::statement('DROP INDEX IF EXISTS payment_plans_business_invoice_unique');
    }
};
