<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sms\TenantNumbers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Puts purchased numbers into the pool tenants are given one from — T137 R8.
 *
 * ⚠️ **THIS IS THE PURCHASE FLOW'S STAND-IN AND SAYS SO.** Doc 51 §2.3's buy-a-
 * number-from-Infobip path is phase 4 and is not built, and R8's *"provisioned
 * from the platform pool"* is, today, an operator buying numbers in Infobip's own
 * portal and telling this application about them. Recording a number here does
 * not purchase one, register one, or make one reachable — it says that one this
 * platform already holds may be given to the next tenant who signs up.
 *
 * ⛔ **SO THE NUMBERS PASSED IN MUST BE ONES WE ACTUALLY HOLD, UNDER THE GOAIEZ
 * 10DLC BRAND.** A number recorded here that Infobip has not issued to us is the
 * worst kind of wrong: the tenant it is assigned to looks correctly provisioned,
 * every screen agrees, and every message they send is filtered while every inbound
 * they are sent never arrives — because the number belongs to somebody else. There
 * is no check that can be made from here, which is exactly why it is written here.
 *
 * ⚠️ **A LOADED NUMBER SITS `provisioning` AND CANNOT SEND**, which is what keeps
 * it out of `NumberSelector`'s shared-pool branch while it waits, and what keeps
 * `sms:register-sender`'s active shared number out of reach of a signup. See
 * {@see TenantNumbers::addToPool()} for both halves of that argument.
 *
 * ⚠️ **IDEMPOTENT, LIKE `sms:register-sender`**, and for its reason: an operations
 * command gets re-run, and a re-run must not refuse with a constraint violation
 * an operator has to tell apart from a real failure.
 */
#[Signature('sms:load-number-pool
    {numbers* : One or more E.164 numbers this platform holds, to be given to tenants}')]
#[Description('Record purchased numbers as assignable, one per tenant at signup (T137 R8)')]
final class LoadNumberPool extends Command
{
    /** Who the record names for something no person in this company did in the app. */
    private const string ACTOR = 'system:pool-load';

    public function handle(TenantNumbers $numbers): int
    {
        /** @var list<string> $requested */
        $requested = (array) $this->argument('numbers');

        $added = 0;
        $known = 0;

        foreach ($requested as $e164) {
            try {
                $number = $numbers->addToPool($e164, self::ACTOR);
            } catch (InvalidArgumentException $e) {
                // ⚠️ REPORTED AND THE RUN CONTINUES, RATHER THAN ABORTING THE
                // BATCH. An operator pasting ten numbers with one typo should end
                // up with nine loaded and one named, not with nothing loaded and
                // no way to tell which was the bad one.
                $this->components->error($e->getMessage());

                continue;
            }

            if ($number->wasRecentlyCreated) {
                $added++;
                $this->components->info("{$number->e164} is now assignable.");

                continue;
            }

            $known++;
            $this->components->info(sprintf(
                '%s is already recorded, in state %s. Nothing changed.',
                $number->e164,
                $number->state->value,
            ));
        }

        if ($added === 0 && $known === 0) {
            return self::FAILURE;
        }

        $this->components->info("Added {$added} number(s); {$known} already recorded.");

        // ⚠️ SAID EVERY TIME, ON `sms:register-sender`'s PRECEDENT, BECAUSE THE
        // ROWS DO LESS THAN THEY LOOK LIKE THEY DO. They make a tenant
        // resolvable from an inbound message and give R7 a number to text back
        // from. They do not enable sending: `SMS_DRIVER`, `sms.enabled` and the
        // 10DLC campaign each still decide that on their own.
        $this->components->warn(
            'Each of these will be given to the next tenant to register, one per tenant. Recording a '
            .'number does not purchase it, register it, or enable sending.'
        );

        return self::SUCCESS;
    }
}
