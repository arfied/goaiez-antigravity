<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A contract that exists so other lanes can build against it, reached before
 * the lane that owns it has filled it in.
 *
 * ⚠️ **THIS TYPE EXISTS BECAUSE OF THE FAILURE SHAPE CLAUDE.md OPENS WITH** —
 * a table, column or control with no writer (272), sixteen instances and
 * counting, whose tell is that *"an isolation test passes perfectly against a
 * table nothing writes."* T176 §5's day-0 plan is stub-first on purpose: L3, L6
 * and L7 build against the agent thread-state, links-registry and
 * campaign-context contracts while L5 fills them in. That plan manufactures
 * three controls with no writer, deliberately, for a few days.
 *
 * ⛔ **SO THE ABSENCE IS LOUD RATHER THAN SILENT.** Each contract is bound to an
 * implementation that throws this, naming the patch that owes it. A lane that
 * calls one early gets a sentence telling it which patch it is waiting on; the
 * alternative — no binding at all — gets a container resolution error naming an
 * interface, and the alternative that actually causes the 272 shape is a null
 * implementation returning `null` or `[]`, which reads as *"this tenant has no
 * links"* rather than *"this is not built yet"* and is indistinguishable from a
 * correct empty answer at every call site.
 *
 * ⚠️ **IT IS NOT A FEATURE FLAG.** Nothing catches it, nothing degrades around
 * it, and it must never be caught to produce a fallback — a caught
 * "unbuilt" is exactly the silent inertness this type exists to prevent. It
 * fails the request, in development, before the patch ships.
 *
 * ## All three of T176's day-0 contracts are built, and nothing throws this today
 *
 * ✅ `LinkRegistry` was built by P6 and `AgentThreads` by P3, both on
 * 2026-08-15; `CampaignContextResolver` by P20 on 2026-08-16. Each `Unbuilt*`
 * class was deleted in the commit that replaced it and each binding moved to a
 * method of its own in `AppServiceProvider` — `registerLinkRegistry()`,
 * `registerAgentThreads()`, `registerCampaignContextResolver()`.
 *
 * ⛔ **THIS DOCBLOCK IS WHERE THAT ARGUMENT NOW LIVES, BECAUSE THE METHOD THAT
 * HELD IT IS DELETED** (4237). `AppServiceProvider::registerDayZeroContracts()`
 * was left binding nothing, and a private method that binds nothing is the shape
 * CLAUDE.md names in its own recurring-failures list: it reads as a live
 * registration point, so the next day-0 stub would be bound *there* rather than
 * in a method of its own — undoing the very tidying the paragraph above records.
 * The argument had to survive the method, and this class is where a lane writing
 * the next stub necessarily arrives.
 *
 * ⚠️ **SO THE RULES, RESTATED FOR THE NEXT ONE**: bind the contract to an
 * implementation that throws this; give the binding a `register*()` method of
 * its own; and delete the `Unbuilt*` class in the same commit that builds the
 * real one, because a refusing implementation left bound beside a real one is
 * how a suite goes green against the wrong one.
 *
 * ⚠️ **AND NOTHING IN `app/` THROWS THIS TODAY**, which
 * `Architecture/ConventionsTest`'s *"nothing in the application catches an
 * unbuilt day-0 contract"* now asserts out loud rather than leaving to be
 * discovered — a lint whose offender population is empty **and whose subjects
 * are also gone** passes vacuously (256), and that test is written so that the
 * next `throw` reddens it and brings whoever added it here.
 */
final class UnbuiltPatch extends RuntimeException
{
    /**
     * @param  string  $patch  The patch that owes the implementation, in T176's
     *                         own numbering — 'P3', 'P6', 'P20'.
     * @param  string  $contract  The interface the caller asked the container for.
     * @param  string  $owes  What the finished thing will do, in one clause, so
     *                        the caller can tell whether it should wait or has
     *                        reached for the wrong contract entirely.
     */
    public static function for(string $patch, string $contract, string $owes): self
    {
        return new self(
            "{$contract} is a day-0 contract with no implementation yet: {$patch} owes {$owes}. "
            .'It is bound to a refusing implementation deliberately, so that building against the '
            .'contract early is safe and calling it early is loud. If you need the behaviour now, '
            ."build {$patch} — do not catch this."
        );
    }
}
