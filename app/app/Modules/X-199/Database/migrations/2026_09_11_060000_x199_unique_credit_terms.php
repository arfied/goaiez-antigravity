<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A customer has one terms row. Its two writers are TermsSetAction's updateOrCreate() and
 * InvoiceEngine::issueInvoice()'s firstOrCreate(), both keyed on (business_id, customer_id), whose
 * createOrFirst() catches a unique violation and returns the row that won. Without a unique index
 * nothing raises, so two concurrent writers both insert and the customer carries two terms rows.
 * customer_id is nullable (a merged contact nulls it) and Postgres treats NULLs as distinct, so rows
 * orphaned that way never collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_terms')) {
            DB::statement(
                'CREATE UNIQUE INDEX credit_terms_business_customer_unique
                 ON credit_terms (business_id, customer_id)'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS credit_terms_business_customer_unique');
    }
};
