<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AutopilotJob;
use App\Jobs\RecoverRestedNumber;
use App\Services\Config\DefaultsRegistry;
use App\Services\Sms\NumberRecovery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Doc `51` §11's `RecoveryRelease` — the daily sweep that walks a rested number
 * back into service, and then back under the automatic health trigger.
 *
 * ⛔ **WITHOUT IT THE CONTAINMENT DISABLES ITSELF ONE RELEASE AT A TIME**
 * (3796, 3988). See {@see NumberRecovery} for the full argument, including why
 * doc 51 §5.3's pool-alarm hold is deliberately **not** implemented rather than
 * written as a guard that could never fire.
 *
 * ## Two paths, and they are not symmetric — `RollUpNumberHealth`'s shape
 *
 * A tenant-owned number is one dispatch of {@see RecoverRestedNumber}: one job,
 * one tenant, one number, and all of `AutopilotJob`'s machinery — the kill
 * switch, the suspension and pause checks, the run row, the activity feed —
 * applies for free. The shared Lane A pool number belongs to no tenant, so it
 * cannot go through that job at all; its hop is performed **directly** here.
 *
 * ⚠️ **AND THERE IS NO PER-TENANT ENUMERATION**, unlike the rollup beside it.
 * `phone_numbers` carries no global scope and no tenant predicate in its RLS
 * policy — the shared pool row belongs to nobody, so a scope would hide exactly
 * the rows this sweep exists for — so the due set is one indexed query over the
 * platform's own inventory. Nothing here reads a tenant-owned table, which is
 * what makes that safe.
 *
 * ⚠️ **IT IS IDEMPOTENT AND ORDER-INDEPENDENT.** The query selects on
 * timestamps against the clock and {@see NumberRecovery::advance()} re-asks the
 * same question under a row lock, so a second run the same minute finds
 * everything already moved on, and a run missed for a week catches up
 * everything that came due meanwhile.
 */
#[Signature('numbers:recover-rested')]
#[Description('Walk rested numbers back into service once their cooldown has elapsed (doc 51 §5.3)')]
final class RecoverRestedNumbers extends Command
{
    public function handle(NumberRecovery $recovery, DefaultsRegistry $registry): int
    {
        // Checked once, before anything is enumerated — `RollUpNumberHealth`'s
        // reason exactly: a tenant-owned number would refuse individually and
        // correctly inside AutopilotJob, but only after opening a `skipped` run
        // row per number, and the shared pool number below goes through no
        // AutopilotJob at all to refuse through.
        if (AutopilotJob::killSwitchThrownFor('numbers.recovery_release')) {
            $this->info('Number recovery is switched off; nothing was released.');

            return self::SUCCESS;
        }

        // ⚠️ `int()` rather than `intOr(…, 0)`, so an unconfigured install gets
        // the reviewed manifest seed — `ReturnParkedNumbers`' note, and 2861's
        // lesson one service over. A key the manifest has never heard of, or a
        // figure the owner has withheld, raises out of here and the sweep does
        // nothing at all, which is the direction a re-arm has to fail in.
        $cooldownDays = $registry->int('numbers.quarantine_cooldown_days');
        $reentryStep = $registry->int('numbers.recovery_reentry_step');

        if ($cooldownDays <= 0) {
            // ⛔ Said out loud rather than passed over, on
            // `ReturnParkedNumbers`' argument: a scheduled task printing
            // "nothing to do" is how a switched-off mechanism stays switched
            // off for a year. Here the consequence is that every automatically
            // quarantined number rests for ever and the platform's only exit
            // from a quarantine is an operator typing a command.
            //
            // ⚠️ **A WARNING, NOT A RETURN.** The recovering → active hop below
            // is gated by its own figure and must still run: holding a number
            // in `recovering` is not the safe side, because that is the one
            // sendable state the health trigger never re-evaluates.
            $this->warn(
                'numbers.quarantine_cooldown_days is not set to a positive number, so no quarantined '
                .'number will ever rest its way back into service. Set it (doc 51 §5.3 says 7) or '
                .'numbers:release-quarantine is the only way out of a quarantine.'
            );
        }

        $released = 0;
        $dispatched = 0;

        foreach ($recovery->due($cooldownDays, $reentryStep) as $number) {
            if ($number->business_id === null) {
                $moved = $recovery->advance($number->id, null, $cooldownDays, $reentryStep);

                if ($moved !== null) {
                    $released++;
                }

                continue;
            }

            RecoverRestedNumber::dispatch(
                $number->business_id,
                $number->location_id,
                $number->id,
                $cooldownDays,
                $reentryStep,
            );

            $dispatched++;
        }

        $this->info(
            "Moved {$released} platform number(s) on and queued {$dispatched} tenant number(s) "
            ."for recovery, after a {$cooldownDays}-day cooldown."
        );

        return self::SUCCESS;
    }
}
