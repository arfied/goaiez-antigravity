<?php

declare(strict_types=1);

use App\Services\Sms\TenantNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop `businesses.messaging_mode` and `businesses.dedicated_number_id` — one
 * unbuilt feature expressed twice, by owner ruling of 2026-08-23 (8390).
 *
 * Both come from `26` §1.2 step 2, a single line inside `SwapNumberJob`:
 * *"Set businesses.dedicated_number_id, messaging_mode='tenant_brand'"*. No
 * `SwapNumberJob` exists, no `ProvisionNumberJob` exists, and there is no
 * `MessagingMode` enum — the job that was to write both columns was never
 * built, so neither column ever acquired a writer.
 *
 * ⚠️ `dedicated_number_id` could not have worked even if one had been built
 * (8295): it is a `uuid`, it names a row in `phone_numbers`, and
 * `phone_numbers.id` is `bigIncrements`. A `uuid` cannot hold a `bigint`.
 *
 * ⛔ THE FEATURE IS NOT BEING REMOVED — A BROKEN SECOND EXPRESSION OF IT IS.
 * What tenant-dedicated numbering actually runs on is `phone_numbers`, and it
 * is fully alive: `phone_numbers.business_id` is a constrained `foreignId`,
 * {@see TenantNumbers::forBusiness()} filters on it, the
 * partial unique index `phone_numbers_one_live_per_business` carries R8's
 * one-live-number-per-tenant invariant, and `Livewire\Account\Calls` renders
 * the result. These two columns are a duplicate of a working path, not the
 * only expression of a missing one.
 *
 * ⚠️ `messaging_mode` HAD ONE READER AND IT IS A REFUSAL TO READ IT.
 * `Feedback\FeedbackSubmission` records that deriving `captured_by` from this
 * column *"was considered and rejected"* (decision 322) — capture provenance
 * is not which number sends. Nothing changes for that file; the argument it
 * preserves survives the column, which is why the comment there is left alone.
 */
return new class extends Migration
{
    /**
     * The check runs with `FORCE` lifted, and that is the whole reason it can
     * fail at all.
     *
     * ⛔ MIGRATIONS RUN AS `goaiez_owner`, WHICH **OWNS** `businesses`, AND
     * `businesses` IS `FORCE ROW LEVEL SECURITY` — so the owner gets the
     * policy too. Its policy is `id = current_setting('app.business_id')`, and
     * a migration establishes no tenant, so `SELECT count(*) FROM businesses`
     * returns **0 on a database holding any number of rows**. Measured on
     * 2026-08-23 against a row deliberately set to `tenant_brand`: the app
     * connection counted 0 and the migrate connection counted 0.
     *
     * A guard written the obvious way would therefore be decision 256's lint
     * that matches nothing — green on every database, including the one it
     * exists to stop. `NO FORCE` is lifted for the length of the count and
     * restored in a `finally`, so it is restored even on the throwing path
     * rather than only by the surrounding transaction's rollback.
     */
    public function up(): void
    {
        $survivors = $this->rowsHoldingAValueNobodyWrote();

        if ($survivors > 0) {
            throw new RuntimeException(
                "Refusing to drop businesses.messaging_mode / businesses.dedicated_number_id: {$survivors} row(s) "
                .'hold a value no code in this repository can have written. The owner ruled on 2026-08-23 (8390) '
                .'that both columns are dead; a row carrying `tenant_brand` or a dedicated number id falsifies '
                .'that premise, so this is a question for the owner rather than something to drop through. '
                .'Capture the rows first: SELECT id, messaging_mode, dedicated_number_id FROM businesses '
                ."WHERE messaging_mode IS DISTINCT FROM 'platform' OR dedicated_number_id IS NOT NULL;"
            );
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['messaging_mode', 'dedicated_number_id']);
        });
    }

    /**
     * Recreates both columns exactly as `create_businesses_table` declared
     * them.
     *
     * ⚠️ They come back at the END of the table rather than between
     * `data_classification` and `marketing_sends_enabled`, because PostgreSQL
     * appends. Nothing here depends on ordinal position — the schema document's
     * column lint compares sets — but a `pg_dump` diff against a database that
     * never ran this pair will not be byte-identical, which is a property of
     * `DROP COLUMN` rather than of this migration.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('messaging_mode')->default('platform');
            $table->uuid('dedicated_number_id')->nullable();
        });
    }

    /**
     * Count rows holding anything but the shipped default, with row-level
     * security genuinely out of the way.
     */
    private function rowsHoldingAValueNobodyWrote(): int
    {
        DB::statement('ALTER TABLE businesses NO FORCE ROW LEVEL SECURITY');

        try {
            /** @var object{c: int|string} $row */
            $row = DB::selectOne(<<<'SQL'
                SELECT count(*) AS c
                  FROM businesses
                 WHERE messaging_mode IS DISTINCT FROM 'platform'
                    OR dedicated_number_id IS NOT NULL
            SQL);

            return (int) $row->c;
        } finally {
            DB::statement('ALTER TABLE businesses FORCE ROW LEVEL SECURITY');
        }
    }
};
