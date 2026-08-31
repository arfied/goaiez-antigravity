<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NumberState;
use App\Services\Sms\NumberLifecycle;
use App\Services\Sms\NumberRecovery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

/**
 * Let a resting number send again — doc `51` §5.3's *"Ops may release early
 * with a typed reason"*, row 4 slice 6 phase 3 (AG6).
 *
 * ⛔ **THIS SHIPS IN THE SAME SLICE AS THE AUTOMATIC TRIGGER BECAUSE THE
 * TRIGGER IS UNSHIPPABLE WITHOUT IT.** `NumberHealthService::evaluateQuarantine()`
 * can now stop a number automatically, and §5.3's cooldown-driven recovery —
 * the path that would ordinarily bring it back — is phase 4. Without this
 * command there would be **no way at all, anywhere in `app/`, to un-quarantine
 * a number**: nothing transitions out of `quarantined`, so on the shared Lane A
 * pool number a single bad afternoon would stop every text on the platform
 * until somebody wrote SQL by hand.
 *
 * ⚠️ **IT RELEASES TO `recovering`, NEVER TO `active`.** `recovering` is
 * sendable ({@see NumberState::maySend()}), so the number is working again
 * immediately — but the state says what actually happened, and doc 51 §6.2
 * weights a recovering number below a healthy one once rotation exists. Walking
 * it to `active` would assert a clean bill of health nobody checked, which is
 * `TenantNumbers::claimForTenant()`'s own refusal one state over.
 *
 * ⚠️ **AND A RECOVERING NUMBER IS NOT RE-EVALUATED BY THE TRIGGER** —
 * `NumberHealthService`'s `EVALUABLE` list, named in plain text here because
 * Pint promotes a `{@see}` into a real `use` statement and this file has no
 * business importing the scorer: the rolling
 * 24-hour window still holds the traffic that caused the quarantine, so
 * re-evaluating would undo this command within the hour, every time.
 *
 * ⛔ **THAT MADE THIS COMMAND A ONE-WAY DOOR ON ITS OWN, AND THE AG6 FIX WAVE
 * IS WHERE THAT WAS NOTICED.** A released number sat outside every automatic
 * trigger *permanently* — §5.3's cooldown walk back to `active` was phase 4 and
 * nothing performed it. So a false positive correctly released on Monday could
 * not be stopped on Friday when the number turned out to be genuinely sick. The
 * first answer was {@see QuarantineNumber}, §5.2's own third trigger row,
 * shipped beside this one: 3769's argument that an automatic containment needs
 * a human exit, applied to the other end of the same door.
 *
 * ✅ **AND THE DOOR SWINGS BOTH WAYS AS OF 2026-08-20 (6420).** `numbers:recover-rested`
 * walks a `recovering` number to `active` once its re-entry climb has elapsed —
 * see {@see NumberRecovery} — so a number released by this command re-arms the
 * automatic trigger by itself within days, without anybody typing anything.
 * ⚠️ **The window is real and is not zero**: for those days the number sends at
 * full capacity and nothing can stop it automatically, which is why
 * {@see QuarantineNumber} stays exactly as necessary as it was.
 *
 * ⚠️ **WHAT IS STILL OWED IS §5.4's SECOND STRIKE** — a number that keeps
 * needing this command still earns no retire recommendation, so the operator is
 * the only thing that notices a pattern.
 *
 * The reason, the actor, the normalisation, the lookup and the audit entry are
 * all {@see NumberStateCommand}'s, shared with the manual quarantine because
 * every one of those disciplines is the same discipline at both ends.
 */
#[Signature('numbers:release-quarantine
    {e164 : The number to release, in E.164 or any form we can read (+15551234567)}
    {--reason= : Why it is being released. Required — it is filed on the state change}
    {--actor= : Who is doing this. Required — it is filed as the actor}')]
#[Description('Release a resting number back into service, with a typed reason (doc 51 §5.3)')]
final class ReleaseQuarantinedNumber extends NumberStateCommand
{
    public function handle(NumberLifecycle $lifecycle): int
    {
        $result = $this->move(
            $lifecycle,
            NumberState::Recovering,
            '%s is recovering and may send again.',
        );

        if ($result === self::SUCCESS) {
            // ⚠️ SAID EVERY TIME. The number is back in service without any of
            // doc 51 §5.3's re-entry clamp: the warmup curve is still unbuilt,
            // and `numbers:recover-rested` implements that step's *duration*
            // rather than its daily caps — so whoever released it is still the
            // clamp on volume.
            $this->components->warn(
                'It re-enters at full capacity: doc 51 §5.3\'s warmup caps are not built, and the health '
                .'trigger does not re-evaluate a recovering number. numbers:recover-rested walks it to '
                .'active in a few days, which is when the trigger can stop it again by itself; until then '
                .'use numbers:quarantine if it turns out to be sick.'
            );
        }

        return $result;
    }
}
