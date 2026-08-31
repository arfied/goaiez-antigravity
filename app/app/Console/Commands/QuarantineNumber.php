<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NumberState;
use App\Services\Sms\NumberLifecycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

/**
 * Rest a number because a person says so — doc `51` §5.2's third trigger row,
 * *"Ops manual | typed reason"*, built in the AG6 fix wave.
 *
 * ⛔ **WITHOUT THIS THE CONTAINMENT IS SINGLE-USE PER NUMBER.**
 * `numbers:release-quarantine` moves a rested number to `recovering`, and
 * `NumberHealthService`'s evaluable list deliberately excludes that state — the
 * rolling 24-hour window still holds the traffic that caused the quarantine, so
 * re-evaluating would undo the release within the hour. §5.3's cooldown walk
 * back to `active` is phase 4 and nothing in `app/` performs it. **So after one
 * release a number sits outside every automatic trigger, permanently.** An
 * operator who correctly releases a false positive on Monday had, until this
 * command, no way at all to stop the same number on Friday when it turned out to
 * be genuinely sick.
 *
 * ⚠️ **IT IS NOT A SECOND SCORER AND I45 IS NOT WEAKENED.** I45 forbids *"a
 * second `NumberHealthService` or a parallel quarantine path"* — a second piece
 * of code that *decides* a number is unhealthy from signals. This decides
 * nothing: a human does, and types why. The `MessagingTest` lint that confines
 * `NumberState::Quarantined` gains this file as a named exception with that
 * argument, rather than the argument living only here.
 *
 * ⚠️ **AND IT DOES NOT PRETEND TO BE §5.4's SECOND STRIKE.** Nothing counts
 * quarantines per thirty days and nothing recommends a retirement; that stays
 * phase 4. This is one control, driven by a person, with their name on it.
 *
 * ⚠️ **A QUARANTINE STOPS OUTREACH AND NOT THE CARRIER-MANDATED REPLIES.**
 * `NumberSelector::forComplianceReply()` still answers HELP and confirms STOP
 * from a resting number, which is the AG6 fix wave's first finding and the
 * reason this command is safe to reach for at all.
 */
#[Signature('numbers:quarantine
    {e164 : The number to rest, in E.164 or any form we can read (+15551234567)}
    {--reason= : Why it is being rested. Required — it is filed on the state change}
    {--actor= : Who is doing this. Required — it is filed as the actor}')]
#[Description('Rest a sending number by hand, with a typed reason (doc 51 §5.2)')]
final class QuarantineNumber extends NumberStateCommand
{
    public function handle(NumberLifecycle $lifecycle): int
    {
        $result = $this->move(
            $lifecycle,
            NumberState::Quarantined,
            '%s is resting and will send no outreach until it is released.',
        );

        if ($result === self::SUCCESS) {
            // ⚠️ SAID EVERY TIME, BECAUSE THE SHARED LANE A NUMBER IS THE ONE
            // MOST LIKELY TO BE TYPED HERE. Resting it stops every text on the
            // platform for every tenant without their own number — which is
            // exactly what an operator may intend, and exactly what they must
            // not discover afterwards.
            $this->components->warn(
                'Every tenant sending from this number is now unable to text. STOP confirmations and '
                .'HELP replies still go out; nothing else does.'
            );
        }

        return $result;
    }
}
