<?php

declare(strict_types=1);

namespace App\Contracts\Links;

use App\Enums\TenantLinkKind;
use App\Models\Conversation;
use App\Services\Links\TenantLink;

/**
 * What links a business has given its assistant, and how one reaches a message
 * (T176 P6, R13 and R14).
 *
 * A day-0 contract: L5's skills 5, 6 and 7 gate on it, L7 builds the "Teach your
 * assistant" editor behind it, and P6 owns the implementation.
 *
 * ✅ **P6 HAS LANDED AND THE REFUSING IMPLEMENTATION IS GONE** (decisions
 * 3989–3994, and 4046 for this correction). `UnbuiltLinkRegistry` was deleted in
 * the same commit that bound `App\Services\Links\TenantLinks`, which is what
 * `AppServiceProvider`'s day-0 docblock requires; the `@throws UnbuiltPatch
 * while P6 is unbuilt` this file carried until then is removed rather than left
 * to read as a live warning. **A stale claim in a contract docblock is what
 * stops the next reader looking** — `CLAUDE.md` 2505's shape, in the one file
 * every lane building against this interface opens first.
 *
 * ## R13 is the whole shape of this interface
 *
 * The capability-gating law: *"each agent skill lights up only when its
 * grounding exists … missing grounding = the skill is absent and the agent falls
 * back to capture + owner handoff. The agent never invents a price, an
 * appointment time, an arrival window, or a link."*
 *
 * ⚠️ **SO A MISSING LINK IS `null`, AND `null` MUST REACH THE PROMPT AS AN
 * ABSENT SKILL RATHER THAN AN EMPTY ONE.** The failure this prevents is not the
 * agent erroring — it is the agent being *told* it can book while holding
 * nothing to book with, which is precisely the state in which a model invents a
 * plausible URL. P19's link-invention refusal suite is the test of it.
 *
 * ⛔ **AND EVERY LINK LEAVES THROUGH {@see shortLinkFor()} (R14).** There is no
 * method on this interface that returns a sendable string, deliberately.
 */
interface LinkRegistry
{
    /**
     * The business's booking link, or `null` when it has set none.
     */
    public function booking(): ?TenantLink;

    /**
     * The business's payment link, or `null` when it has set none.
     *
     * ⚠️ A link with no fee set does not ground skill 6 — see
     * {@see TenantLink::groundsFeeCollection()}. The link may still exist for a
     * customer who asks to pay, so this returning non-`null` is not on its own
     * permission to quote a call-out charge.
     */
    public function payment(): ?TenantLink;

    /**
     * Every document this business shares, keyed by slug.
     *
     * ⚠️ **AN EMPTY ARRAY IS A REAL ANSWER** — most businesses share none — and
     * it switches skill 7 off. It is not a failure and must not be logged as one.
     *
     * @return array<string, TenantLink>
     */
    public function documents(): array;

    /**
     * One named document, or `null` when this business does not share it.
     *
     * ⛔ **THE SLUG IS UNTRUSTED.** Skill 7 picks it from what a customer asked
     * for, which means the value reaching this method has passed through a
     * model and originally came from a member of the public. An implementation
     * resolves it against this tenant's own documents and returns `null` for
     * anything else — never a path, never a lookup that could leave the tenant.
     */
    public function document(string $slug): ?TenantLink;

    /**
     * Mint the short link that actually goes in a message (R14).
     *
     * ⚠️ **PER SEND, NOT PER LINK.** T176: *"per-send tokens, click → CRM
     * timeline."* Two customers sent the same price sheet get two tokens, so a
     * click resolves to a person and lands on the right timeline. Caching one
     * token per link collapses that back into a page view.
     *
     * ⛔ **THE CONVERSATION MUST BELONG TO THE CURRENT TENANT.** An
     * implementation refuses one that was carried across a tenant boundary in
     * memory — a queued job, a console command, a support path — rather than
     * minting a token whose click would be filed against the wrong business's
     * timeline. `App\Services\Links\TenantLinks` raises a `RuntimeException` for
     * it.
     *
     * @param  Conversation  $conversation  The thread the link is being sent on
     *                                      — what makes the click attributable.
     * @return string The short URL, already inside the composer's ≤159-character
     *                budget. ⚠️ The link is what the character law is measured
     *                *with*, never after.
     *
     * ⛔ **THE FIRST CALLER IN `app/` IS `AgentComposer::facts()`** (4271), and
     * until it existed this method had none — 272's shape on the one method R14
     * is made of. What it mints there is a **booking** link on the thread being
     * answered; P7's two surfaces deliberately do not call it, because a page
     * view and a published review reply are not sends and have no conversation
     * to key a token on (`LinksTest`'s R14 list carries that argument).
     *
     * ⚠️ **THE `@throws UnbuiltPatch` THIS BLOCK CARRIED IS GONE BECAUSE P6 IS
     * BUILT.** The refusing implementation was deleted in P6's own commit and a
     * lint keeps it deleted, so the annotation described a failure mode no
     * caller could reach — 2505's shape in a contract's own docblock.
     */
    public function shortLinkFor(TenantLink $link, Conversation $conversation): string;

    /**
     * Which skills this business's links currently ground (R13).
     *
     * Used by the agent when assembling its own capabilities, and by the wizard
     * to tell an owner what stays switched off.
     *
     * @return array<value-of<TenantLinkKind>, bool>
     */
    public function grounded(): array;
}
