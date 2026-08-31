<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NumberStateChange;
use App\Models\PhoneNumber;
use App\Services\Sms\NumberLifecycle;
use App\Support\Identifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The full transition history of one number, read rather than reconstructed
 * by hand — doc `51` §2.4, §10.
 *
 * ⛔ **`number_state_changes` HAD A WRITER, A BOT EXCLUSION ELSEWHERE ON THE
 * SEND PATH, AND NO READER AT ALL** — wave 38 lane E. It is *"the only
 * durable record of a shared number's quarantine history"*
 * ({@see NumberStateChange}'s own docblock), and until this command the only
 * way to answer *"why did this tenant's messages stop"* was hand-written SQL
 * against a table nothing in `app/` had ever read back.
 *
 * ⚠️ **A SECOND SCORER'S FIRST QUESTION AND THIS COMMAND'S ANSWER IS "NO".**
 * I45 forbids a second `NumberHealthService` or a parallel quarantine path —
 * a piece of code that *decides* a number is unhealthy from signals. This
 * decides nothing and moves nothing: it is a read, formatted for a terminal,
 * of exactly the rows {@see NumberLifecycle} already wrote.
 *
 * ⚠️ **EVERY ROW EVER FILED AGAINST THIS E.164, NOT ONLY THE LIVE ROW'S.**
 * {@see NumberStateCommand::find()}'s own docblock records that a number can
 * be held, retired and re-provisioned, leaving two `phone_numbers` rows
 * sharing one E.164 — and `number_state_changes` is keyed on `number_id`,
 * never on the number itself. An operator asking "why did this number stop
 * working" is asking about the E.164, not about whichever row happens to be
 * live today, so this looks up every `phone_numbers` id that has ever carried
 * it and reads all of their history together, oldest first.
 *
 * ⚠️ **NOT `NumberStateCommand`'s SUBCLASS, DELIBERATELY.** That base class
 * exists to share `--reason`/`--actor` validation and the transition call
 * between the two commands that *move* a number. This moves nothing, needs
 * neither flag, and inheriting from a base built around a write would leave
 * two unused required options on a read-only command — the opposite of the
 * "typed reason wherever a human acted" discipline that base states, applied
 * to an act that is not one.
 */
#[Signature('numbers:history
    {e164 : The number to look up, in E.164 or any form we can read (+15551234567)}')]
#[Description('The full transition history of one number, across every row it has ever held (doc 51 §2.4)')]
final class NumberHistory extends Command
{
    public function handle(): int
    {
        $typed = trim((string) $this->argument('e164'));

        // The same normaliser `NumberStateCommand::move()` uses, for the same
        // reason: an operator at 2am types `512-555-9999`, and an exact match
        // against the stored E.164 would tell them nothing matches when
        // something does.
        $e164 = Identifier::phone($typed);

        if ($e164 === null) {
            $this->components->error(sprintf('%s is not a phone number this application can read.', $typed));

            return self::FAILURE;
        }

        // Every row this E.164 has ever held, not only the live one — see the
        // class docblock. Ordered oldest-first so the numbers line up: the
        // lowest id provisioned first.
        $numberIds = PhoneNumber::query()
            ->where('e164', $e164)
            ->orderBy('id')
            ->pluck('id');

        if ($numberIds->isEmpty()) {
            $this->components->error(sprintf('No number in the inventory matches %s.', $e164));

            return self::FAILURE;
        }

        $changes = NumberStateChange::query()
            ->whereIn('number_id', $numberIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($changes->isEmpty()) {
            // Structurally unreachable today — NumberLifecycle::transitionTo()
            // files the Provisioning row before a number can exist at all —
            // and stated rather than assumed, because a read command is the
            // wrong place to trust an invariant it did not write.
            $this->components->warn(sprintf('%s exists but has no recorded transitions.', $e164));

            return self::SUCCESS;
        }

        $this->table(
            ['When', 'From', 'To', 'Actor', 'Reason'],
            $changes->map(static fn (NumberStateChange $change): array => [
                $change->created_at?->toDayDateTimeString() ?? '—',
                $change->from_state === null ? '(new)' : $change->from_state->value,
                $change->to_state->value,
                $change->actor,
                $change->reason ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
