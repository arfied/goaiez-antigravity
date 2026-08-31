<?php

declare(strict_types=1);

namespace App\Support\Support;

use App\Enums\SupportMacroSlot;
use App\Services\Support\SupportMacros;

/**
 * THE SUPPORT MACRO LIBRARY — T308 §A3 and §A4, loaded into `support_macros` by
 * `php artisan macros:sync` (CC-5 §4).
 *
 * S-0 through S-10 in one voice, verbatim from T308 with one class of change
 * made deliberately and recorded below. `CampaignPackCatalog`'s sibling and the
 * same house shape: a reviewed declaration in `app/Support`, one idempotent
 * command, rows underneath that the Ops composer renders as insert-buttons.
 *
 * ## ⚠️ WHAT WAS TRANSLATED, AND WHY IT IS NOT A REWRITE (CC-5 §0)
 *
 * T308 writes its fill-ins as `{status}`, `{old}`, `{cancel_link}` and so on,
 * and its *stage directions* in the same braces: `{If down:}`, `{THEN, only
 * after the link:}`, `{make-good options per the rules}`. Two different things
 * in one notation, and the guard that stops a slot reaching a tenant cannot tell
 * them apart. So:
 *
 *  - **Fill-ins become {@see SupportMacroSlot} cases** — same syntax as the
 *    campaign slots (braces, lower case, snake_case), a different vocabulary,
 *    which is CC-5 §0 honoured rather than forked. `{old}`/`{new}` are spelled
 *    `{old_price}`/`{new_price}`, because "old" alone is not a word a person
 *    reading a half-filled macro can act on.
 *  - **Stage directions become plain English or disappear.** `{If down:}` is
 *    written out, because it is a sentence the agent keeps or deletes.
 *    S-4's `{THEN, only after the link:}` is dropped entirely: it instructs an
 *    ordering the text already has, and T308's EXIT-FIRST rule is satisfied by
 *    the link being first — *"never a question standing between a person and the
 *    door."*
 *
 * ## ⛔ S-3 HAS NO AUTHORED BODY AND IS BOUND, NOT PASTED
 *
 * T308 does not write S-3 out. It says *"the FM macro of record, verbatim
 * placement — the promise kept in one paste"*, which is a pointer to the written
 * guarantee rather than a body. So S-3 carries
 * {@see SupportMacroSlot::GuaranteeSentence}, resolved from
 * `legal.guarantee_sentence` at the moment it is inserted — CC-5 §2's rule
 * (*"bind the sentence via CC-4's one-source key, never paste it"*) reaching
 * the support library as well as the lifecycle rungs. While that key is unset,
 * **S-3 is not offered at all**: see {@see SupportMacros::insertable()}.
 *
 * ## ⚠️ T308's BANNED-PHRASES LIST IS ENFORCED, NOT MERELY QUOTED
 *
 * *"we apologize for any inconvenience" · "per our policy" · "as previously
 * stated" · "unfortunately" as an opener.* `SupportMacroTest` greps every
 * seeded body for all four, because a registered rule with no lint is a rule
 * that erodes — and the failure mode here is the one T308 names: *"corporate
 * sorry-speak is how trust dies politely."*
 */
final class SupportMacroCatalog
{
    /**
     * Every macro this application ships with, in library order.
     *
     * ⚠️ **S-0 IS FIRST AND IS POSITION ZERO.** T308 puts the escalation line in
     * §A4 rather than in the numbered library, and CC-5 §4 names it *"macro
     * S-0"*. It leads because it is the one an agent reaches for when the
     * library has failed them, and T308's NAME-CHANGE RULE makes it the one that
     * must never be forgotten: *"when a human takes over a thread, the thread
     * SAYS so … Nobody ever wonders whether they're talking to the machine."*
     *
     * @return list<array{key: string, title: string, body: string, position: int}>
     */
    public static function macros(): array
    {
        return [
            [
                'key' => 's-0',
                'title' => 'A human has taken the thread over',
                'position' => 0,
                'body' => "Matt here — I've taken over your thread. Give me "
                    .SupportMacroSlot::Time->placeholder()
                    ." and you'll have a real answer.",
            ],
            [
                'key' => 's-1',
                'title' => "Where's my text-back?",
                'position' => 1,
                'body' => "I checked your line just now — here's what I see: "
                    .SupportMacroSlot::Status->placeholder()
                    .". If it's down: that's on us; here's what happens next: "
                    .SupportMacroSlot::Step->placeholder()
                    .", and I'll confirm to this thread the minute it's flowing.",
            ],
            [
                'key' => 's-2',
                'title' => 'It quoted the wrong price',
                'position' => 2,
                'body' => 'Good catch — the price list had '
                    .SupportMacroSlot::OldPrice->placeholder()
                    .' where it should have '
                    .SupportMacroSlot::NewPrice->placeholder()
                    .". I've fixed the row; every quote from this second uses the right number. "
                    ."Say the word and I'll send a corrected text to that customer too.",
            ],
            [
                'key' => 's-3',
                'title' => 'The guarantee make-good',
                'position' => 3,
                'body' => SupportMacroSlot::GuaranteeSentence->placeholder()
                    ." That's the promise, and it stands — so here's the make-good, today: "
                    .SupportMacroSlot::MakeGoodOptions->placeholder().'.',
            ],
            [
                'key' => 's-4',
                'title' => 'How do I cancel?',
                'position' => 4,
                'body' => 'Right here: '
                    .SupportMacroSlot::CancelLink->placeholder()
                    ." — it takes about a minute, no call needed. If you've got 30 seconds after, "
                    ."I'd genuinely value one line on what didn't work.",
            ],
            [
                'key' => 's-5',
                'title' => 'The refund ask',
                'position' => 5,
                'body' => "Here's the honest picture: plans are pay-as-you-go with no refunds — and "
                    ."here's what I CAN do: "
                    .SupportMacroSlot::MakeGoodOptions->placeholder()
                    .". Also, canceling takes one minute whenever you want, so you're never locked in.",
            ],
            [
                'key' => 's-6',
                'title' => 'Do you sell my data?',
                'position' => 6,
                'body' => "No — and not the polite kind of no. We don't sell it, we don't "
                    .'pool it across businesses, and the privacy page says exactly that in plain words: '
                    .SupportMacroSlot::PrivacyLink->placeholder().'.',
            ],
            [
                'key' => 's-7',
                'title' => "Why can't I text yet?",
                'position' => 7,
                'body' => "Carrier registration is the one clock we can't speed up — it takes real days "
                    ."and I'd rather tell you that than pretend. Here's where yours stands: "
                    .SupportMacroSlot::Status->placeholder()
                    .'. The moment it clears, everything fires.',
            ],
            [
                'key' => 's-8',
                'title' => 'The bug report',
                'position' => 8,
                'body' => 'Thank you — this is exactly the kind of report that makes the product better. '
                    ."Here's what I've logged: "
                    .SupportMacroSlot::Repro->placeholder()
                    .". I can't promise a date; I can promise you'll hear in this thread when it ships.",
            ],
            [
                'key' => 's-9',
                'title' => 'The angry thread',
                'position' => 9,
                'body' => "You're right to be frustrated — "
                    .SupportMacroSlot::WhatHappened->placeholder()
                    ." shouldn't have happened. Here's the fix, in order: "
                    .SupportMacroSlot::Steps->placeholder()
                    .". I'm on this thread until it's done.",
            ],
            [
                'key' => 's-10',
                'title' => 'The feature ask',
                'position' => 10,
                'body' => "Honest answer: it's not in the product today, and I don't promise roadmaps — "
                    .'when something ships, it announces itself. What I CAN do now: '
                    .SupportMacroSlot::NearestPath->placeholder().'.',
            ],
        ];
    }
}
