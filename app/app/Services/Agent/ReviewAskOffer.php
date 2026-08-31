<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Models\FeedbackPage;
use App\Models\ShortLink;

/**
 * The one address skill 13 is allowed to say, minted — T176 §2.2 row 13, P12.
 *
 * ⛔ **THE WHOLE POINT IS THAT THE URL EXISTS BEFORE THE MODEL IS TOLD IT MAY
 * OFFER ONE.** P4 shipped row 13's prompt line — *"you may offer the review link
 * once"* — with nothing anywhere that puts a link in front of the model, while
 * rail 5 says *"never invent a link … use only what you were given"*. A model
 * told it may send a link and given none is the documented way a plausible URL
 * gets invented, and it is R13's own failure mode with a different noun. This
 * object is what closes that: no offer, no capability, no line in the briefing.
 *
 * ⚠️ **A SHORT LINK RATHER THAN THE `/f/{slug}` URL ITSELF, AND R14 IS WHY** —
 * every agent send is short-linked, so that a click lands on the timeline and so
 * that the address fits the segment budget. The target is the tenant's own
 * feedback page; the token is per contact, so a click answers *"this person
 * clicked"* rather than *"somebody did"*.
 */
final readonly class ReviewAskOffer
{
    public function __construct(
        /**
         * The short-linked address, exactly as the assistant must reproduce it.
         */
        public string $url,
        public ShortLink $link,
        public FeedbackPage $page,
    ) {}

    /**
     * Whether an outbound body actually carried the offer.
     *
     * ⛔ **THIS IS WHAT DECIDES THE ONE ASK IS SPENT.** The model may be handed
     * the link and choose not to use it — the prompt line is permissive
     * (*"you may"*), which is deliberate, because a customer who has not
     * finished their question should not be asked for a review. Recording the
     * ask on the strength of the *offer* would burn a business's single chance
     * on a turn where nothing was asked.
     *
     * ⚠️ **A SUBSTRING TEST, AND IT IS EXACT RATHER THAN FUZZY.** The token is
     * twelve random characters, so a false positive would need the model to
     * reproduce it by accident; a false *negative* — the model mangling the
     * URL — leaves the ask unspent and the link unclicked, which is the harmless
     * direction and the one worth erring in.
     */
    public function appearsIn(string $body): bool
    {
        return str_contains($body, $this->url);
    }
}
