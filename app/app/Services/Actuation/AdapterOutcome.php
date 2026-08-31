<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\AdapterOutcomeState;

/**
 * What an adapter did, in the one shape every caller can branch on.
 *
 * ⚠️ **A REFUSAL IS A RETURN VALUE, NOT AN EXCEPTION**, on `VoiceProvider`'s
 * rule: a vendor that cannot be reached is an ordinary condition on somebody
 * else's website, and the caller — not the driver — decides what it means. What
 * the caller must not do is record a change as applied on the strength of a call
 * that failed, which is why {@see SiteChanges::apply()} stamps `applied_at` only
 * on `ok`.
 *
 * ⛔ **THERE ARE THREE ANSWERS AND THERE USED TO BE TWO, AND THE MISSING ONE
 * COST AN OWNER A PAGE THEY WERE TOLD WAS GONE** (6040, 6041, closed at 6140).
 * See {@see AdapterOutcomeState} for the argument in full. The short of it:
 * *"nothing is published at that address"* is not the same fact as *"we took the
 * page down"*, and until this type could say so the adapter had to pick one of
 * two answers that were both wrong.
 *
 * ⚠️ **`$ok` SURVIVES AND IS DERIVED FROM `$state`, WHICH IS THE ONE SOURCE OF
 * TRUTH.** It is kept because *"did the customer's website change"* is a genuine
 * yes/no that half a dozen callers legitimately ask before stamping a column —
 * and it is derived rather than passed so the pair cannot disagree, which is
 * `SiteSnapshot`'s private-constructor argument on the write side.
 * ⛔ **A CALLER THAT PRINTS A SENTENCE MUST READ `$state` AND NOT `$ok`**: the
 * two non-`Ok` states are a website that refused and a page we never found, and
 * one sentence covering both is how 6040 was worded in the first place.
 *
 * ⚠️ **`$pageRef` IS THE SEAM 5966 ASKED FOR AND IT IS OPAQUE ABOVE THE
 * ADAPTER.** It is whatever *this* adapter needs to find the same page again —
 * `pages/51` on WordPress — recorded on `site_changes.written_page_ref` at apply
 * time and handed back inside the {@see ChangeSet} at revert time. **Nothing
 * above the adapter parses it**, and nothing parses it out of {@see self::$detail}
 * either, which would be 5811's prose-matching failure with a write attached.
 */
final readonly class AdapterOutcome
{
    /**
     * Did the customer's website actually change?
     *
     * ⚠️ **DERIVED FROM {@see self::$state} IN THE CONSTRUCTOR, NEVER PASSED
     * IN** — see the class docblock for why it survives at all.
     */
    public bool $ok;

    private function __construct(
        public AdapterOutcomeState $state,
        public string $detail,
        public ?string $pageRef = null,
    ) {
        $this->ok = $state === AdapterOutcomeState::Ok;
    }

    /**
     * ⚠️ **`$pageRef` IS THE ADAPTER'S OWN NAME FOR THE PAGE IT JUST WROTE**,
     * and it is optional because not every adapter has one — the log driver
     * writes nowhere, and a future adapter may address pages by URL alone.
     */
    public static function ok(string $detail, ?string $pageRef = null): self
    {
        return new self(AdapterOutcomeState::Ok, $detail, $pageRef);
    }

    public static function failed(string $detail): self
    {
        return new self(AdapterOutcomeState::Failed, $detail);
    }

    /**
     * We did not write, because we could not establish that the page in front
     * of us is the page this change set is about.
     *
     * ⛔ **NOTHING ON THE CUSTOMER'S WEBSITE CHANGED, AND OUR CHANGE IS STILL
     * ON IT.** Both halves are load-bearing: the first is why this is not a
     * failure to apologise for, and the second is why the row must stay open for
     * the nightly retry rather than being closed as done.
     */
    public static function unverified(string $detail): self
    {
        return new self(AdapterOutcomeState::Unverified, $detail);
    }
}
