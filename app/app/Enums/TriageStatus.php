<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a recovery conversation has got to (DATA-MODEL §5.7).
 *
 * The column existed as an uncast string with the vocabulary written in a
 * migration comment. Row 3 slice E is its first writer, and CLAUDE.md is
 * explicit: a string column cast to a PHP backed enum, never a database enum
 * and never a bare string somebody has to remember the spelling of.
 *
 * `NoResponse` IS NOT `Closed`. A customer who never replied and an owner who
 * decided there was nothing more to do are different outcomes, and only one of
 * them says anything about whether the recovery worked. Collapsing them would
 * make "how often does triage actually recover somebody" unanswerable.
 *
 * ✅ **EVERY CASE HAS A WRITER SINCE 2026-08-12 (2700–2705), AND FOUR OF THE
 * FIVE ARE THE OWNER'S.** `ReviewRouter::openTriage()` wrote `Open` and nothing
 * in `app/` wrote anything else — so `ProofNumbers`' owner-facing *"customers
 * recovered"* counted a state no code could reach and was structurally zero
 * (decision 2689). The four terminal states are now written by
 * `ReviewRouter::recordTriageOutcome()`, from the *Win back customers* screen.
 *
 * ⚠️ **THE METHODS BELOW ARE REFERENCED IN PROSE RATHER THAN WITH `{@see}`**:
 * Pint's `fully_qualified_strict_types` turns a `{@see}` into a real `use`
 * statement, and an enum importing a service and a nav helper is a dependency
 * nobody asked for, created by a formatter, in the file least likely to be
 * re-read.
 *
 * ⚠️ **THEY ARE READ AS THE OWNER'S OUTCOMES, NOT AS AN AI LOOP'S STATES**
 * (2701). `17` TRIAGE-01/02 describe `Escalated` as the bot standing down on a
 * legal threat and `NoResponse` as a send that timed out — **and no AI triage
 * loop exists, nothing sends on this path, and nothing appends to
 * `transcript`** (942). A button asserting "the customer did not reply" to a
 * message this system never sent would be a state claiming a fact nothing here
 * can produce. What the owner *can* assert is what they themselves did and what
 * came of it, so that is what these mean and what {@see self::label()} says. The
 * AI-loop readings return with TRIAGE-01 and do not conflict: the same four
 * outcomes, reached by a different party.
 */
enum TriageStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Escalated = 'escalated';
    case NoResponse = 'no_response';
    case Closed = 'closed';

    /**
     * What the owner reads on the card, and on the button that puts it there.
     *
     * ⚠️ **THE LABEL AND THE ACTION SHARE THEIR WORDS ON PURPOSE** (`22`: an
     * action keeps its verb through the flow, Publish → Published). "Mark as won
     * back" leaves the card reading "Won back"; "Couldn't reach them" leaves it
     * reading "Couldn't reach them". A button whose past tense is a different
     * phrase makes the owner check whether the click did what they asked.
     *
     * ⚠️ **AND THE VOCABULARY IS THE FEED'S, NOT A SECOND ONE.**
     * `AutopilotActionType::TriageResolved` has said *"Won back an unhappy
     * customer"* since before this screen existed, and `28` §3.3's number is
     * *"unhappy customers recovered"*. One event described in three vocabularies
     * is how an owner ends up unable to connect a number on Home to a card on a
     * queue — the same argument `TenantSuspended`'s title makes about the status
     * page.
     */
    public function label(): string
    {
        return match ($this) {
            // Not "Open". The owner is not reading a ticket state, they are
            // being told there is somebody they have not got back to.
            self::Open => 'Needs you',
            self::Resolved => 'Won back',
            self::Escalated => 'Flagged as serious',
            self::NoResponse => "Couldn't reach them",
            self::Closed => 'Nothing more to do',
        };
    }

    /**
     * The wording on the button that puts a conversation into this state.
     *
     * ⚠️ **A SECOND METHOD RATHER THAN `label()` REUSED, BECAUSE TWO OF THE
     * FOUR CHANGE TENSE AND TWO DO NOT.** `22`'s rule is Publish → Published:
     * the action is imperative and the state it produces is past. "Mark as won
     * back" → "Won back" and "Flag as serious" → "Flagged as serious" both move;
     * *"Couldn't reach them"* and *"Nothing more to do"* are already statements
     * the owner is making about what happened, and rewriting them into
     * imperatives ("Record no response") would put our vocabulary back into a
     * sentence that reads perfectly in theirs. Rendering `label()` on a button
     * gave *"Flagged as serious"* as a control, which asks the owner to press a
     * past tense.
     *
     * ⚠️ **`Open` HAS NO ACTION WORDING, AND THE `match` IS EXHAUSTIVE SO IT
     * CANNOT BE FORGOTTEN.** Reopening is a correction rather than an outcome
     * and carries its own words on the screen; "mark this as needing you" is not
     * something an owner wants to say. `WinBack::record()` refuses `Open`
     * outright, so this string would never be read.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Open => 'Put back on my list',
            self::Resolved => 'Mark as won back',
            self::Escalated => 'Flag as serious',
            self::NoResponse => "Couldn't reach them",
            self::Closed => 'Nothing more to do',
        };
    }

    /**
     * Whether this conversation is still waiting on somebody.
     *
     * The queue's own grouping and `OwnerNavBadges`'s
     * count both ask this, rather than each spelling `=== self::Open`. A badge
     * that says 3 beside a list showing 2 is `FollowUps`' own stated trap, and
     * the fix there was the same: one predicate, asked twice.
     *
     * ⚠️ **`Escalated` IS NOT OPEN AND THAT IS DELIBERATE.** It is a terminal
     * outcome of the recovery attempt — the owner has decided this is beyond a
     * phone call — and leaving it in the "needs you" count would badge the nav
     * forever with a conversation whose next step is not on this screen.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Whether reaching this state means the customer was won back.
     *
     * ⚠️ **`Resolved` ALONE, AND THE OTHER THREE ARE NOT NEAR-MISSES.** `28`
     * §3.3's integrity rule forbids a modeled number, and `ProofNumbers`'
     * `recovered` is the one on the owner's Home screen. "Couldn't reach them"
     * and "Nothing more to do" are the honest record that this one did not work;
     * counting either would inflate the most-trusted number this product says,
     * to the person least able to check it.
     */
    public function isRecovery(): bool
    {
        return $this === self::Resolved;
    }
}
