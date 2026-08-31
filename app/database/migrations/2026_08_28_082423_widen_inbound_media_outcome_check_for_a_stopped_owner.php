<?php

declare(strict_types=1);

use App\Enums\InboundMediaOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The closed-set CHECK that would otherwise refuse a stopped account holder's
 * refusal row — wave 41 lane E, decision 11100.
 *
 * `inbound_media.outcome` is a string column cast to a PHP backed enum
 * (`CLAUDE.md`, never a database enum) and its creating migration closes the set
 * with a CHECK built from `::cases()`. **A fresh `migrate` therefore already
 * accepts the new case and a deployed database does not**, which is the whole
 * reason this file exists and is also why its absence would be invisible to
 * every test here: the suite runs `migrate:fresh`, so the constraint it builds
 * already contains the case, and the `SQLSTATE[23514]` would appear for the
 * first time on the running install — inside a carrier webhook, on an account
 * holder who had just asked us to stop.
 *
 * `2026_08_17_170754_widen_stored_object_state_checks_for_pruning.php` is the
 * precedent this copies, down to the `down()` behaviour.
 *
 * ## ⚠️ ONLY THE *NAME* CHECK MOVES
 *
 * `inbound_media_stored_rows_carry_bytes` is untouched, exactly as it was for
 * `Pruned`. Its second arm already reads `outcome <> 'stored' AND storage_path
 * IS NULL AND …`, and a `refused_owner_stopped` row carries no path, no disk, no
 * content type, no size and no checksum — nothing was fetched, so there is
 * nothing for it to carry. **A migration that widened *that* constraint would be
 * decision 216's second layer relaxed to fit a feature**, and this codebase does
 * not rewrite the safety net to approve of what it caught.
 *
 * ## ⚠️ NO BACKFILL, AND THERE COULD NOT BE ONE
 *
 * This makes a state legal and writes no row. The rows the defect produced are
 * `stored` rows with real objects behind them, and turning one into a refusal
 * here would be a lie in an append-only record — the object would still be in
 * the bucket. It would also be an `UPDATE` of a tenant-owned, FORCE-RLS table
 * from a migration, which establishes no tenant and so **matches zero rows and
 * reports success** (`CLAUDE.md`). What is owed for the photographs already kept
 * is a ruling and then a purge, and both are named at 11103.
 *
 * ⚠️ **`down()` IS DELIBERATELY NOT SAFE IN THE PRESENCE OF SUCH ROWS.** It
 * rebuilds the CHECK from the enum minus the new case, and Postgres validates a
 * new constraint against the rows already there — so a rollback after one
 * account holder's attachment has been refused fails loudly instead of leaving a
 * row whose value its own column's constraint forbids.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recreateOutcomeCheck($this->outcomeValues());
    }

    public function down(): void
    {
        $this->recreateOutcomeCheck($this->without(
            $this->outcomeValues(),
            InboundMediaOutcome::RefusedOwnerStopped->value,
        ));
    }

    /**
     * @return list<string>
     */
    private function outcomeValues(): array
    {
        return array_map(
            static fn (InboundMediaOutcome $case): string => $case->value,
            InboundMediaOutcome::cases(),
        );
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function without(array $values, string $excluded): array
    {
        return array_values(array_filter(
            $values,
            static fn (string $value): bool => $value !== $excluded,
        ));
    }

    /**
     * @param  list<string>  $values
     */
    private function recreateOutcomeCheck(array $values): void
    {
        $list = $this->quoted($values);

        DB::statement('ALTER TABLE inbound_media DROP CONSTRAINT inbound_media_outcome_is_known');

        DB::statement(<<<SQL
            ALTER TABLE inbound_media
                ADD CONSTRAINT inbound_media_outcome_is_known
                CHECK (outcome IN ({$list}))
        SQL);
    }

    /**
     * ⚠️ **THESE ARE ENUM BACKING VALUES AND NEVER USER INPUT**, so the
     * interpolation above is over a list this file has just built from
     * `::cases()`. Quoted here rather than at the call site, because two
     * spellings of one list is how one of them goes stale.
     *
     * @param  list<string>  $values
     */
    private function quoted(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            $values,
        ));
    }
};
