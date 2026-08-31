<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NumberState;
use App\Models\PhoneNumber;
use App\Services\Sms\NumberLifecycle;
use App\Support\Identifier;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use LogicException;

/**
 * What the two Ops number-state controls share — doc `51` §5.2's third trigger
 * row and §5.3's early release, which are one control from opposite ends.
 *
 * ⛔ **THEY SHIP AS A PAIR AND 3769's OWN ARGUMENT IS WHY.** That decision
 * refused to ship an automatic containment with no human way out. The AG6 fix
 * wave found the same hole from the other side: releasing moves a number to
 * `recovering`, which `NumberHealthService`'s evaluable list deliberately
 * excludes, so **after one release the number is permanently outside every
 * automatic trigger** — and doc 51 §5.3's cooldown walk that would put it back
 * is phase 4. A false positive correctly released on Monday could not be stopped
 * on Friday by anything short of hand-written SQL, which is the state 3769
 * declared unshippable, reached from the far end. The manual quarantine is
 * §5.2's own third trigger row (*"Ops manual | typed reason"*), and it was
 * neither built nor on 3774's list of deliberate omissions.
 *
 * ⚠️ **THIS IS NOT A SECOND QUARANTINE PATH IN I45's SENSE.** I45 forbids a
 * second *scorer* — a parallel piece of code that decides a number is unhealthy.
 * Nothing here decides anything: a person does, and says why in their own words.
 * Both subclasses go through {@see NumberLifecycle}, which is still the one
 * writer of a state and the one thing that files the history row.
 *
 * ## Three disciplines, all of them the same discipline
 *
 * ⚠️ **THE REASON IS REQUIRED AND HAS NO DEFAULT.** Doc 51 §2.4 wants a typed
 * reason wherever a human acted and §9's Ops controls are "all typed-reason". A
 * default would make `number_state_changes` say a person did this without saying
 * why, which is the row somebody reads first when it happens again.
 *
 * ⚠️ **AND SO IS THE ACTOR, WHICH IS NEW IN THE AG6 FIX WAVE.** The release
 * command filed `ops:cli` — a string naming no human at all — for what is the
 * most answerable-for act in this domain: putting the platform's only sending
 * number back into service. `29` §2 rule 42 wants the person, not the terminal.
 *
 * ⛔ **AND THE AUDIT ENTRY IS ACTUALLY WRITTEN NOW.** `NumberLifecycle` files
 * `audit_log` only when the resolved tenant *is* the number's owner, and a
 * console command resolves no tenant — so even a tenant-owned number's release
 * fell through to `Log::info`. {@see self::move()} establishes the owning tenant
 * around the transition, so the entry lands where rule 42 wants it. **A
 * platform-pool number still cannot have one**, because `audit_log` is
 * tenant-owned and RLS-`FORCE`d and the shared number belongs to nobody (1630) —
 * that is stated rather than worked around.
 */
abstract class NumberStateCommand extends Command
{
    /**
     * Move the named number, or explain why nothing happened.
     *
     * @param  string  $success  A `sprintf` template taking the E.164.
     */
    protected function move(NumberLifecycle $lifecycle, NumberState $to, string $success): int
    {
        $reason = $this->requiredOption(
            'reason',
            'A typed reason is filed on the state change and on the audit entry. Pass --reason="…".',
        );

        if ($reason === null) {
            return self::FAILURE;
        }

        $actor = $this->requiredOption(
            'actor',
            'Name the person doing this — it is filed as the actor. Pass --actor="…".',
        );

        if ($actor === null) {
            return self::FAILURE;
        }

        $typed = trim((string) $this->argument('e164'));

        // ⚠️ **NORMALISED RATHER THAN MATCHED LITERALLY.** `phone_numbers.e164`
        // is stored in E.164 and an operator at 2am types `512-555-9999`. The
        // old exact match told them no number in the inventory matched, which is
        // both false and the least useful thing to say to somebody trying to
        // stop a number. `Identifier::phone()` is the one normaliser in this
        // application and it fails closed, so an unparseable input still stops
        // here rather than becoming a `LIKE`.
        $e164 = Identifier::phone($typed);

        if ($e164 === null) {
            $this->components->error(sprintf('%s is not a phone number this application can read.', $typed));

            return self::FAILURE;
        }

        $number = $this->find($e164);

        if (! $number instanceof PhoneNumber) {
            // ⚠️ NAMED PLAINLY. This is one of our own numbers, never a
            // customer's — the same argument `phone_numbers`' creating
            // migration makes for storing it in plain text.
            $this->components->error(sprintf('No number in the inventory matches %s.', $e164));

            return self::FAILURE;
        }

        try {
            $this->transition($lifecycle, $number, $to, 'ops:'.$actor, $reason);
        } catch (LogicException) {
            // ⚠️ **THE LEGALITY TABLE IS THE GUARD, NOT A STATE CHECK IN A
            // SUBCLASS.** `NumberLifecycle::transitionTo()` holds the one table,
            // so asking it is strictly stronger than re-deciding here — and a
            // second copy of the rule is the second source of truth that drifts.
            // The exception is translated into a sentence rather than a stack
            // trace, because an operator typing the wrong number is an ordinary
            // mistake and not a programming error.
            $this->components->error(sprintf(
                '%s is in state %s, and that is not a state this can move from. Nothing changed.',
                $number->e164,
                $number->state->value,
            ));

            return self::FAILURE;
        }

        $this->components->info(sprintf($success, $number->e164));

        return self::SUCCESS;
    }

    /**
     * A trimmed option, or null having already said what is missing.
     */
    private function requiredOption(string $name, string $explanation): ?string
    {
        $raw = $this->option($name);
        $value = is_string($raw) ? trim($raw) : '';

        if ($value === '') {
            $this->components->error($explanation);

            return null;
        }

        return $value;
    }

    /**
     * The live row for an E.164, when the same number has been held twice.
     *
     * ⚠️ **A NUMBER CAN APPEAR MORE THAN ONCE AND THE OLD LOOKUP TOOK WHICHEVER
     * ROW THE DATABASE OFFERED FIRST.** 2686's park returns a retired number to
     * the assignable pool, so a number held, retired and re-provisioned has two
     * rows — and `first()` with no ordering is a coin toss between them, which on
     * a stop-the-number command is the wrong kind of coin toss. Terminal rows
     * sort last and the newest row wins, so this resolves to the row that is
     * actually in service.
     */
    private function find(string $e164): ?PhoneNumber
    {
        return PhoneNumber::query()
            ->where('e164', $e164)
            ->orderByRaw(
                'case when state in (?, ?) then 1 else 0 end',
                [NumberState::Retired->value, NumberState::Released->value],
            )
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The transition, inside the owning tenant when there is one.
     */
    private function transition(
        NumberLifecycle $lifecycle,
        PhoneNumber $number,
        NumberState $to,
        string $actor,
        string $reason,
    ): void {
        $owner = $number->business_id;

        if ($owner === null) {
            $lifecycle->transitionTo($number, $to, $actor, $reason);

            return;
        }

        Tenancy::actingAs($owner, function () use ($lifecycle, $number, $to, $actor, $reason): void {
            $lifecycle->transitionTo($number, $to, $actor, $reason);
        });

        // Never leave a security context established after a console command —
        // the rule `RollUpNumberHealth` states at the identical point.
        Tenancy::forgetAll();
    }
}
