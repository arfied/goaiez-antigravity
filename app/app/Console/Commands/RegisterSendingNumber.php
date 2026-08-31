<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NumberRole;
use App\Enums\NumberState;
use App\Models\PhoneNumber;
use App\Services\Sms\NumberLifecycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Puts the configured `INFOBIP_SENDER` into the number inventory.
 *
 * ⚠️ **THIS EXISTS SO `phone_numbers` IS NOT A WRITERLESS TABLE ON THE DAY IT
 * SHIPS** — `CLAUDE.md`'s first recurring failure shape, fifteen instances and
 * counting, and its tell is *"an isolation test passes perfectly against a table
 * nothing writes"*. The purchase flow that would ordinarily fill this table is
 * doc 51 §2.3 and phase 4; without this command the seam would be complete,
 * green, and inert, and every send would go out on `InfobipClient`'s
 * bootstrap fallback forever.
 *
 * ⚠️ **THE ROW IS RECORDED `active`, NOT `provisioning`, AND THE REASON IS THAT
 * ITS EARLIER LIFECYCLE HAPPENED SOMEWHERE ELSE.** Doc 51 §2.4 has a number born
 * `provisioning` and walking registering → warming → active, which is exactly
 * right for a number this application buys. This one was bought, registered
 * under the 10DLC brand (1562) and put into service before the table existed, so
 * there are no transitions to record — only the state we first observed it in.
 * Writing the row `provisioning` would be truthful about the schema and false
 * about the world, and it would **silently stop every text**: a provisioning
 * number is not sendable, so `NumberSelector` would refuse and `PlatformTexter`
 * would return null on a platform that had been sending fine a moment earlier.
 * That is recorded in `state_reason` on the row rather than only here.
 *
 * ⚠️ **`lane` IS LEFT NULL AND MUST STAY NULL** — `BUILD-PLAN` §2.10.5 and 1563:
 * which lane the registered brand serves is unresolved and is not this
 * command's to answer. Writing `platform` here because the number happens to be
 * shared today would answer an open question by accident, in a seed, where
 * nobody would look for the ruling.
 *
 * ⚠️ **IDEMPOTENT, BECAUSE IT IS A DEPLOY STEP AND DEPLOY STEPS GET RE-RUN.**
 * Running it twice must not create a second row for the same number — the
 * partial unique index would refuse the insert with a constraint violation, and
 * an operator reading a stack trace from a re-run has no way to tell it from a
 * real failure. The second run reports what is already there and changes
 * nothing, including the state: a number an operator deliberately quarantined
 * must not be marched back to `active` by a deploy.
 */
#[Signature('sms:register-sender
    {--number= : E.164 to record. Defaults to the configured INFOBIP_SENDER}')]
#[Description("Record the platform's configured sending number in the number inventory")]
final class RegisterSendingNumber extends Command
{
    /** Who the record names for something no person in this company did. */
    private const string ACTOR = 'system:sender-registration';

    public function handle(NumberLifecycle $lifecycle): int
    {
        $option = $this->option('number');
        $configured = config('services.infobip.sender');

        $e164 = is_string($option) && trim($option) !== ''
            ? trim($option)
            : (is_string($configured) ? trim($configured) : '');

        if ($e164 === '') {
            // ⚠️ REFUSED RATHER THAN DEFAULTED. `config/services.php` gives the
            // key no default on purpose: a message sent from a number the 10DLC
            // campaign was not registered against is filtered rather than
            // refused, which is indistinguishable from a delivery that never
            // happened. A placeholder row here would put that number in the
            // inventory permanently.
            $this->components->error(
                'No number to record. Set INFOBIP_SENDER, or pass --number=+15551234567.'
            );

            return self::FAILURE;
        }

        $existing = PhoneNumber::query()
            ->where('e164', $e164)
            ->where('state', '!=', NumberState::Released->value)
            ->first();

        if ($existing !== null) {
            $this->components->info(sprintf(
                '%s is already recorded, in state %s. Nothing changed.',
                $existing->e164,
                $existing->state->value,
            ));

            return self::SUCCESS;
        }

        $number = $lifecycle->record(
            e164: $e164,
            // The shared Lane A pool: it belongs to no tenant, which is what
            // `business_id IS NULL` means on this table and what the CHECK
            // requires of this role.
            role: NumberRole::SharedPool,
            state: NumberState::Active,
            actor: self::ACTOR,
            reason: 'Registered retrospectively: this number was purchased, brand-registered and '
                .'in service before the inventory existed, so no earlier transition was observed.',
        );

        $this->components->info(sprintf('Recorded %s as the shared sending number, active.', $number->e164));

        // ⚠️ SAID EVERY TIME, BECAUSE THE ROW DOES LESS THAN IT LOOKS LIKE IT
        // DOES. It records which number sends and lets a quarantine be
        // expressed; it does not turn anything on. `SMS_DRIVER` still decides
        // whether a carrier is reached at all and the 10DLC campaign was
        // filed and REJECTED (11617) — the reason has not been obtained.
        $this->components->warn(
            'Its lane is deliberately unset: which lane the registered brand serves is still open '
            .'(BUILD-PLAN §2.10.5). Recording a number does not enable sending.'
        );

        return self::SUCCESS;
    }
}
