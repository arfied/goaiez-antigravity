<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\IdentifierHashEpochStatus;
use App\Models\IdentifierHashEpoch;
use App\Services\Consent\IdentifierHashEpochs;
use App\Services\Consent\SuppressionRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Reports whether this install can still read the hashes it has stored, and is
 * the only way to accept the loss when it cannot.
 *
 * ⚠️ **THE OFF SWITCH ON A FAIL-CLOSED GATE HAS TO EXIST OR IT IS A `tinker`
 * SESSION AGAINST PRODUCTION** — 4319's finding, where the only supported way to
 * end the founder pricing window was exactly that. An operator who has
 * deliberately rotated `APP_KEY` and cannot restore the old one needs a
 * documented, attributed way to say so; without one they would reach for the
 * database.
 *
 * ⛔ **AND THE CHEAP WAY OUT IS PRINTED FIRST, EVERY TIME.** Restoring the
 * previous key loses nothing and is almost always the right answer, because a
 * rotation usually reaches here by accident — `composer setup` runs
 * `key:generate` unconditionally.
 *
 * ⚠️ **THERE ARE TWO WAYS TO RESOLVE A REFUSAL AND ONLY ONE OF THEM LOSES
 * ANYTHING** (8185, 8187). `--adopt` records that the key now in `.env` is the
 * one that wrote the hashes already stored here — the ordinary answer for an
 * install that upgraded onto this check — and loses nothing. `--accept-loss`
 * records that the key that wrote them is gone. Asking for both at once is
 * refused rather than resolved in either direction.
 *
 * ⚠️ **IT EXITS NON-ZERO WHEN THE REGISTERS CANNOT ANSWER**, so it is usable as
 * a deploy gate. It is deliberately **not** wired into `composer deploy` here:
 * that file is not this lane's, and a deploy step that refuses is a decision an
 * operator should make with the runbook in front of them rather than one that
 * arrives in a merge.
 *
 * ⛔ **AND BOTH RESOLVING VERBS USED TO EXIT `0` HAVING DONE NOTHING** (8201).
 * Each sits behind `$this->confirm(…, false)`, which under `--no-interaction`
 * **returns the default without asking** — a deploy step, a runbook line,
 * anything without a TTY. Each then printed *"Nothing was adopted."* /
 * *"Nothing was retired."* and returned `SUCCESS`, so **a scripted operator
 * reading exit codes was told it worked** about a platform refusing every send.
 * The contract is that `0` from this command means the registers can answer,
 * and after a declined verb they cannot.
 *
 * ⚠️ **SO BOTH VERBS ARE INTERACTIVE-ONLY, DELIBERATELY.** There is no `--force`
 * and there should not be one: the confirmation is part of the record that a
 * person decided this, and a flag that skips it on the destructive verb is the
 * opposite of 8088's reasoning for having the verb at all. **The bare report
 * path is unchanged** — it asks nothing and stays non-zero on the two unreadable
 * states, which is what makes it a gate.
 */
#[Signature('consent:hash-epoch
    {--adopt : Record that the key now in .env is the one that wrote the hashes already stored here}
    {--accept-loss : Retire every superseded epoch. Every stored suppression stops refusing anybody, permanently}
    {--actor= : Who is deciding; required with --adopt and --accept-loss}
    {--reason= : Why; required with --adopt and --accept-loss}')]
#[Description('Report whether stored suppression hashes are still readable, and adopt or accept the loss if not')]
final class ConsentHashEpoch extends Command
{
    public function handle(IdentifierHashEpochs $epochs, SuppressionRegistry $registry): int
    {
        if ($this->option('adopt') && $this->option('accept-loss')) {
            // ⛔ **THE TWO ANSWER OPPOSITE QUESTIONS AND ONE OF THEM IS
            // IRREVERSIBLE.** Adopting says *these stored rows are readable*;
            // accepting the loss says *they never will be again*. Picking one
            // for an operator who asked for both is how the destructive one gets
            // run by accident.
            $this->components->error(
                'Choose one. --adopt records that the stored hashes are readable under the current '
                .'key; --accept-loss records that they are not and never will be.'
            );

            return self::FAILURE;
        }

        if ($this->option('adopt')) {
            return $this->adopt($epochs);
        }

        if ($this->option('accept-loss')) {
            return $this->acceptLoss($epochs, $registry);
        }

        return $this->report($epochs);
    }

    private function report(IdentifierHashEpochs $epochs): int
    {
        $status = $epochs->status();

        $this->line('Identifier hashing fingerprint: '.$epochs->fingerprint());
        $this->newLine();
        $this->table(
            ['fingerprint', 'first seen', 'by', 'adopted by', 'retired', 'by', 'reason'],
            $epochs->all()->map(fn (IdentifierHashEpoch $epoch): array => [
                mb_substr($epoch->fingerprint, 0, 16).'…',
                $epoch->first_seen_at->toDateTimeString(),
                $epoch->first_seen_by,
                // ⚠️ NAMED IN THE REPORT BECAUSE IT IS THE ONE COLUMN THAT SAYS
                // "a person asserted this rather than a write observing it".
                // A reader working out why the registers are trusted needs to
                // see which rows are evidence and which are a claim.
                $epoch->adopted_by ?? '',
                $epoch->retired_at?->toDateTimeString() ?? '',
                $epoch->retired_by ?? '',
                $epoch->retired_reason ?? '',
            ])->all(),
        );

        return match ($status) {
            IdentifierHashEpochStatus::Unrecorded => $this->unrecorded($epochs),
            IdentifierHashEpochStatus::Current => $this->current($epochs),
            IdentifierHashEpochStatus::Rotated,
            IdentifierHashEpochStatus::Unattributed => $this->unreadable($epochs, $status),
        };
    }

    /**
     * ⛔ **THIS SAID "NO IDENTIFIER HASH HAS EVER BEEN WRITTEN ON THIS INSTALL"
     * AND IT HAD CHECKED NOTHING OF THE KIND** (8183). It was printed whenever
     * the epoch table held no live row, which was also true of an install that
     * upgraded onto this guard holding thousands of suppressions — so the one
     * command an operator runs to ask *are my registers readable?* answered with
     * a fact about a table it had not looked at. The state is now derived from
     * the stores as well, so the sentence is checked; and it says which stores,
     * because that is the part a reader can verify.
     */
    private function unrecorded(IdentifierHashEpochs $epochs): int
    {
        // ⚠️ **THE COPY LIVES ON THE SERVICE NOW** (8202). It used to be here
        // and only here, while `operatorSentence()` threw on this arm — so the
        // sentence an operator reads and the sentence anything else would say
        // about the same state could not be the same string. One source.
        $this->components->info($epochs->operatorSentence());

        return self::SUCCESS;
    }

    private function adopt(IdentifierHashEpochs $epochs): int
    {
        $this->components->warn(
            'This records that a person asserted the APP_KEY now in .env is the one that wrote the '
            .'suppression hashes already stored here. Nothing in this application can check that. '
            .'If it is the wrong key, every stored STOP, DNC and litigator entry goes on refusing '
            .'nobody and this check will never say so again.'
        );

        if (! $this->confirm('Is the current APP_KEY the one those rows were written under?', false)) {
            // ⛔ **NON-ZERO, BECAUSE NOTHING WAS ADOPTED AND THE REGISTERS STILL
            // CANNOT ANSWER** (8201). This returned `SUCCESS` until 2026-08-22,
            // and the sharp edge is not the operator who typed *no*: under
            // `--no-interaction` — a deploy script, a runbook step, any
            // non-TTY — `confirm()` returns the default **without prompting**,
            // so a scripted operator was told `0` by a command that had done
            // nothing at all, about a platform refusing every send. This
            // command's contract is that `0` means the registers can answer,
            // and after a declined adoption they cannot.
            $this->components->error(
                'Nothing was adopted, and stored suppression hashes are still not readable. An '
                .'adoption records that a PERSON asserted which key wrote them, so it is only '
                .'available interactively — there is deliberately no flag that skips the question.'
            );

            return self::FAILURE;
        }

        try {
            $fingerprint = $epochs->adopt((string) $this->option('actor'), (string) $this->option('reason'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Adopted '.mb_substr($fingerprint, 0, 16).'…. Sending is no longer refused for this reason.');

        return self::SUCCESS;
    }

    private function current(IdentifierHashEpochs $epochs): int
    {
        $this->components->info($epochs->operatorSentence());

        return self::SUCCESS;
    }

    private function unreadable(IdentifierHashEpochs $epochs, IdentifierHashEpochStatus $status): int
    {
        $this->components->error(match ($status) {
            IdentifierHashEpochStatus::Rotated => 'Stored suppression hashes are NOT readable by this install.',
            // ⚠️ **THE HONEST WORD IS "UNKNOWN" AND NOT "UNREADABLE"**, and the
            // two have different fixes: one of them is a command that loses
            // nothing. Saying the stronger thing would send an operator to
            // `--accept-loss` for a problem `--adopt` solves.
            default => 'It is not known whether stored suppression hashes are readable by this install.',
        });
        $this->newLine();
        $this->line($epochs->operatorSentence());

        return self::FAILURE;
    }

    private function acceptLoss(IdentifierHashEpochs $epochs, SuppressionRegistry $registry): int
    {
        $actor = (string) $this->option('actor');
        $reason = (string) $this->option('reason');

        $this->components->warn(
            'This records a decision to lose every suppression this platform holds in hash form: '
            .'every carrier STOP on the shared toll-free number, every DNC and litigator entry, '
            .'every lift. Putting the previous APP_KEY back afterwards will not undo it.'
        );

        // ⛔ **THE ONE ARM WHERE `--adopt` IS PROBABLY THE RIGHT ANSWER, SAID
        // BEFORE THE QUESTION RATHER THAN AFTER IT** (8203). `Unattributed` is
        // *we do not know which key wrote these rows*, and it reaches `retire()`
        // with **nothing to retire** — no epoch was ever recorded for the key
        // that wrote them (8187). So the destructive half runs in full and the
        // count of retired epochs is zero, which used to be reported as
        // *"Retired 0 superseded epochs"* and read as though little had
        // happened. It is the same act on this arm as on `rotated`, and it is
        // the arm where it is least likely to be what the operator meant.
        if ($epochs->status() === IdentifierHashEpochStatus::Unattributed) {
            $this->components->warn(
                'Nothing here records which key wrote the stored hashes, so there is no epoch to '
                .'retire and none will be retired — the loss is the whole of what this does. If '
                .'the APP_KEY now in .env is the one those rows were written under, '
                .'`--adopt` loses nothing and is almost certainly what you want. Restore the key '
                .'you believe wrote them first, then adopt.'
            );
        }

        if (! $this->confirm('Accept the loss of every stored suppression?', false)) {
            // ⛔ **NON-ZERO FOR THE SAME REASON `--adopt` IS** (8201). Under
            // `--no-interaction` `confirm()` returns the default without ever
            // prompting, so this printed *"Nothing was retired."* and exited `0`
            // — a scripted operator reading exit codes was told the platform
            // had been resolved when nothing had been touched.
            $this->components->error(
                'Nothing was retired, and stored suppression hashes are still not readable. '
                .'Accepting the loss is only available interactively — the confirmation is part '
                .'of the record that a person decided it.'
            );

            return self::FAILURE;
        }

        try {
            $outcome = $epochs->retire($actor, $reason, $registry);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $retired = count($outcome['retired']);
        $entries = $outcome['register_entries_superseded'];
        $entryWord = 'entr'.($entries === 1 ? 'y' : 'ies');

        // ⚠️ **"Retired 0 superseded epochs" IS TRUE AND READS AS THOUGH LITTLE
        // HAPPENED** (8203), on the one arm where the destructive half runs in
        // full and `--adopt` was probably the right answer. The zero case gets
        // its own sentence, and it leads with what was lost rather than with
        // what was counted.
        $this->components->info($retired === 0
            ? 'No epoch was retired — nothing recorded which key wrote the stored hashes — and '
                .$entries.' compliance register '.$entryWord.' superseded. Every stored STOP, DNC '
                .'and litigator entry has permanently stopped refusing anybody. Sending is no '
                .'longer refused for this reason.'
            : 'Retired '.$retired.' superseded epoch'.($retired === 1 ? '' : 's')
                .' and superseded '.$entries.' compliance register '.$entryWord
                .'. Sending is no longer refused for this reason.');

        // ⛔ **THE RE-IMPORT IS NOT OPTIONAL AND THE GATE NOW SAYS SO TOO.**
        // `SuppressionRegistry::isLoaded()` reads rows by list and channel, so
        // until 8186 the moment the epoch was retired it answered *loaded*
        // again over the very rows just declared unreadable. Those rows are
        // superseded in the same transaction now, so the gate reports every
        // register missing — which is true — and a marketing send stays refused
        // until they genuinely come back from their sources. This line is what
        // tells the operator that the refusal they are about to meet is
        // expected.
        $this->components->warn(
            'Re-import every scrubbing register from its source with `compliance:load-suppressions`. '
            .'Every entry was superseded in the same act, so marketing sends stay refused until '
            .'they are back. Nothing can re-derive opt_outs: those STOPs are gone.'
        );

        return self::SUCCESS;
    }
}
