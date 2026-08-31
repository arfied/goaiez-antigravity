<?php

declare(strict_types=1);

use App\Enums\InboundMediaOutcome;
use App\Enums\VoicemailAudioState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The two closed-set CHECKs that would otherwise refuse a pruned row — decision
 * 4944.
 *
 * `inbound_media.outcome` and `voicemails.audio_state` are both string columns
 * cast to a PHP backed enum (`CLAUDE.md`, never a database enum), and both
 * creating migrations close the set with a CHECK built from `::cases()`. Adding
 * `Pruned` to either enum without this migration produces a `SQLSTATE[23514]`
 * from inside a scheduled sweep — a retention run that reddens the queue on
 * every object it reaches.
 *
 * ## ⚠️ ONLY THE *NAME* CHECKS MOVE, AND NOT THE ONE THAT MATTERS MOST
 *
 * `inbound_media_stored_rows_carry_bytes` is untouched. Its second arm already
 * reads `outcome <> 'stored' AND storage_path IS NULL AND storage_disk IS NULL
 * AND content_type IS NULL AND byte_size IS NULL AND checksum IS NULL`, so a
 * `pruned` row satisfies it exactly and unchanged — which is the whole reason
 * {@see InboundMediaOutcome::Pruned} nulls the checksum along with the rest
 * rather than keeping it. **A migration that widened that constraint to make
 * room for a pruned row still holding a checksum would be decision 216's second
 * layer relaxed to fit a feature**, and this codebase's standing rule is that
 * the safety net is not rewritten to approve of the thing it caught. The
 * set-membership CHECK is a different animal: it exists to keep the column in
 * step with the enum, so widening it *is* keeping it in step.
 *
 * `voicemails` has no path-or-bytes CHECK at all, so it needs only the name.
 *
 * ## ⚠️ NO BACKFILL, AND NOTHING CHANGES STATE HERE
 *
 * This migration makes a state *legal*. It writes no row and it prunes nothing:
 * that is `storage:prune`'s job, and that command does nothing at all until an
 * operator states a period (4942). A migration that also deleted objects would
 * put irreversible destruction of a customer's data behind `php artisan
 * migrate`, where a rollback cannot reach it and `down()` would be a lie.
 *
 * ⚠️ **`down()` IS DELIBERATELY NOT SAFE IN THE PRESENCE OF PRUNED ROWS.** It
 * rebuilds each CHECK from the enum minus `Pruned`, and Postgres validates a new
 * constraint against the rows already there — so a rollback after anything has
 * been pruned fails loudly instead of leaving rows whose value the column's own
 * constraint forbids. That refusal is the correct behaviour and it costs
 * nothing to get.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recreateOutcomeCheck($this->outcomeValues());
        $this->recreateAudioStateCheck($this->audioStateValues());
    }

    public function down(): void
    {
        $this->recreateOutcomeCheck($this->without(
            $this->outcomeValues(),
            InboundMediaOutcome::Pruned->value,
        ));

        $this->recreateAudioStateCheck($this->without(
            $this->audioStateValues(),
            VoicemailAudioState::Pruned->value,
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
     * @return list<string>
     */
    private function audioStateValues(): array
    {
        return array_map(
            static fn (VoicemailAudioState $case): string => $case->value,
            VoicemailAudioState::cases(),
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
     * @param  list<string>  $values
     */
    private function recreateAudioStateCheck(array $values): void
    {
        $list = $this->quoted($values);

        DB::statement('ALTER TABLE voicemails DROP CONSTRAINT voicemails_audio_state_is_known');

        DB::statement(<<<SQL
            ALTER TABLE voicemails
                ADD CONSTRAINT voicemails_audio_state_is_known
                CHECK (audio_state IN ({$list}))
        SQL);
    }

    /**
     * ⚠️ **THESE ARE ENUM BACKING VALUES AND NEVER USER INPUT**, so the
     * interpolation above is over a list this file has just built from
     * `::cases()`. They are quoted here rather than at each call site, because
     * two spellings of the same list is how one of them goes stale.
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
