<?php

declare(strict_types=1);

use App\Models\TenantExport;
use App\Services\Export\ExportBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `44` §10's per-contact export — the scope lives on the row, never in the URL.
 *
 * ⛔ **THE COLUMN IS THE WHOLE SAFETY DESIGN AND THE ALTERNATIVE WAS THE
 * DEFECT.** The obvious shape for "export this one customer" is to mint a signed
 * link naming a customer and filter at download time. That is a data breach with
 * a green suite: the object behind
 * {@see TenantExport::$storage_path} is a whole-account
 * archive, a handler that forgets the filter streams it, and every test that
 * asserts *"the link works"* still passes. So the scope is decided **before a
 * byte is written** — a scoped row builds a scoped object, and the download path
 * can only ever serve whatever object its row names.
 *
 * `null` is an account-wide export and is every row that existed before this
 * migration; a set value is `28` §3.7's *"Advanced adds per-object exports"*.
 * There is no second enum saying which — the column already answers it, and a
 * status word beside a nullable id is the second source of truth `CLAUDE.md`
 * refuses on principle.
 *
 * ## ⚠️ THE FOREIGN KEY IS COMPOSITE, AND THAT IS THE CROSS-TENANT GUARANTEE
 *
 * A plain `customer_id → customers(id)` would happily accept **another tenant's**
 * contact id: `business_id` is not part of that reference, RLS is a runtime
 * predicate rather than a constraint, and a `Tenancy::actingAs()` that the
 * caller got wrong writes a perfectly valid row. `(business_id, customer_id) →
 * customers(business_id, id)` cannot — the pair has to exist together — so the
 * schema refuses the shape rather than depending on every future writer holding
 * the tenant correctly.
 *
 * That reference needs a unique key on the referenced pair, which `customers`
 * did not carry. It is added here and it constrains nothing new: `id` is already
 * the primary key, so `(business_id, id)` is unique for free. It exists so
 * Postgres will accept the reference.
 *
 * ⚠️ **`ON DELETE CASCADE` IS THE SAME ANSWER `business_id` ALREADY GIVES.**
 * Nothing in `app/` hard-deletes a contact — `CustomerEditor::delete()` stamps
 * `deleted_at` — so the only path that removes one is the account cascade, which
 * takes `tenant_exports` with it anyway and which
 * {@see ExportBuilder::purgeAllFor()} clears the objects for first.
 *
 * ⚠️ **NO RLS WORK.** `tenant_exports` is already `ENABLE`+`FORCE`d on
 * `business_id` with a `tenant_isolation` policy from its creating migration,
 * and a column does not change what that policy covers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            // Referenced by the composite key below, and by nothing else. Named
            // explicitly so the reference and the index cannot drift apart on a
            // future rename.
            $table->unique(['business_id', 'id'], 'customers_business_id_id_unique');
        });

        Schema::table('tenant_exports', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable()->after('requested_by');

            // What `ExportBuilder::reusable()` asks on every request: "is there a
            // recent build for *this* scope". Without the scope in the index the
            // question is answerable and the *query* would still have been the
            // defect — see that method for what an unscoped reuse hands back.
            $table->index(['business_id', 'customer_id', 'id'], 'tenant_exports_scope_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_exports
                ADD CONSTRAINT tenant_exports_customer_is_same_tenant
                FOREIGN KEY (business_id, customer_id)
                REFERENCES customers (business_id, id)
                ON DELETE CASCADE
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenant_exports DROP CONSTRAINT tenant_exports_customer_is_same_tenant');

        Schema::table('tenant_exports', function (Blueprint $table): void {
            $table->dropIndex('tenant_exports_scope_index');
            $table->dropColumn('customer_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_business_id_id_unique');
        });
    }
};
