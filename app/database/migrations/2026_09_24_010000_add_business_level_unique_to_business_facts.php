<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * business_facts' unique index includes a nullable location_id, and Postgres
 * treats NULLs as distinct there — two business-level rows with the same key
 * could both insert. A business-level fact is one row per key.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS business_facts_business_key_unique ON business_facts (business_id, key) WHERE location_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS business_facts_business_key_unique');
    }
};
