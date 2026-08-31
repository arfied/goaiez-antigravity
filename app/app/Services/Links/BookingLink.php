<?php

declare(strict_types=1);

namespace App\Services\Links;

use App\Contracts\Links\LinkRegistry;
use App\Exceptions\UnbuiltPatch;
use App\Models\Conversation;

/**
 * The business's booking link, on the two surfaces that are not a message
 * (T176 P7).
 *
 * P6 owns the *store* — {@see LinkRegistry} and {@see TenantLink}. P7 owns the
 * *surfacing*, and this class exists so the two surfaces it covers cannot drift
 * apart on the three rules that govern them.
 *
 * ## R14, and why nothing here mints a short link
 *
 * *"Every agent-sent link rides the short-link service (SL-5): booking, payment,
 * documents, review link — per-send tokens, click → CRM timeline."*
 *
 * ⛔ **BOTH OF P7's SURFACES ARE OUTSIDE THAT SENTENCE, AND FOR TWO DIFFERENT
 * REASONS.**
 *
 * - **The feedback thanks screen** is a page the customer is already looking at.
 *   R14 governs a link that is *sent* — a per-send token exists so a click
 *   resolves to the person it was sent to. Nobody was sent this one; they
 *   arrived, and the page they arrived on is already the click record's context.
 *   A short link here would add a hop, an outage surface and a redirect between
 *   a customer and the business's own scheduler, and buy nothing.
 * - **A review reply** is published text on a public listing, read by everyone
 *   who ever reads that review. {@see LinkRegistry::shortLinkFor()} takes a
 *   {@see Conversation} because a token is minted *per send*; a
 *   review reply has no conversation and no recipient, so there is nothing to
 *   attribute a click to. Minting one token anyway would put a
 *   supposedly-per-send token on a public web page for the whole internet to
 *   click, which is exactly the collapse that method's own docblock warns
 *   against — "caching one token per link collapses that back into a page view".
 *
 * ⚠️ **SO THIS CLASS IS NOT A LOOPHOLE ROUND R14 AND MUST NOT BECOME ONE.** The
 * moment a booking link goes into an SMS, an email or an agent turn, it rides
 * `shortLinkFor()`. Nothing here is on that path, and nothing on that path may
 * call this.
 *
 * ## R13, and why `null` is the whole answer
 *
 * *"Missing grounding = the skill is absent … the agent never invents a price,
 * an appointment time, an arrival window, or a link."* A business that has set
 * no booking link gets a thanks screen with no booking block and a reply
 * composer with no insert control — never a placeholder, a "coming soon", or a
 * disabled button explaining what they are missing. The surface simply does not
 * offer it.
 *
 * ✅ **P6 HAS LANDED, SO THE "UNTIL P6" HALF OF THIS DOCBLOCK IS GONE** (decision
 * 4046). {@see LinkRegistry} resolves to {@see TenantLinks} in a real request,
 * `UnbuiltLinkRegistry` was deleted with the binding, and both surfaces below
 * work today rather than raising. The sentence this replaces said the opposite —
 * that `/f/{slug}/thanks` and `/account/replies` raise until P6 replaces the
 * binding — which is `CLAUDE.md` 2505's shape: a claim that was true when
 * written, restated as a fact about today, in the file a reviewer of these two
 * surfaces opens first.
 *
 * ⛔ **THE RULE IT CARRIED SURVIVES ITS SUBJECT AND IS NOT RELAXED.** Nothing
 * here may catch a day-0 contract's refusal to produce a fallback —
 * {@see UnbuiltPatch}: *"nothing catches it, nothing degrades around it, and it
 * must never be caught to produce a fallback."* Doing so would turn "not built"
 * into "this business set no booking link", a correct-looking answer at every
 * call site. It is now held for **every** file by
 * `Architecture/ConventionsTest`'s *"nothing in the application catches an
 * unbuilt day-0 contract"*.
 *
 * ⛔ **AND THAT LINT HAS NO LIVE SUBJECT TODAY — THIS SENTENCE CLAIMED THE
 * OPPOSITE UNTIL 2026-08-16** (4239). It read *"which still has live subjects in
 * `AgentThreads` and `CampaignContextResolver`, so it does not pass vacuously
 * (256)"*, and composing the lanes made both halves false in one push: P3 built
 * `AgentThreads`, P20 built `CampaignContextResolver`, and **nothing in `app/`
 * throws `UnbuiltPatch` at all** any more — so there is nothing left for any
 * file to catch. 256's shape, in the docblock a reviewer of this class opens
 * first, and 2505's shape as well: a claim that was true when written, restated
 * as a fact about today.
 *
 * ⚠️ **WHAT WOULD RESTORE A SUBJECT IS THE NEXT DAY-0 STUB**, which T176 §5's
 * plan produces every time a lane builds against a contract before its owner
 * fills it in. The lint is kept pre-positioned rather than deleted, because
 * deleting it means the next stub lands unguarded — and the test now asserts
 * that the *thrower* count is zero as well, so the day one reappears it reddens
 * and brings whoever added it to {@see UnbuiltPatch}'s docblock, which is where
 * the rule now lives.
 */
final readonly class BookingLink
{
    public function __construct(private LinkRegistry $registry) {}

    /**
     * The link to render as an ordinary anchor on a public page, or `null`.
     *
     * ⚠️ **THE TENANT IS AMBIENT, NOT A PARAMETER**, because {@see LinkRegistry}
     * takes none — it answers for whichever business is current. On `/f/{slug}`
     * that is established by `ResolveFeedbackPage` from the slug before the
     * controller runs, and on `/account/replies` by `ResolveTenant` from the
     * session. Neither surface may pass a business id of its own choosing, which
     * is what keeps one tenant's link off another tenant's page.
     */
    public function forPublicPage(): ?TenantLink
    {
        return $this->usable();
    }

    /**
     * The line an owner inserts into a review reply, or `null`.
     *
     * Plain text and the raw destination, because a Google review reply is
     * plain text — see the class docblock for why it is not short-linked.
     *
     * ⚠️ **THE BUSINESS'S OWN LABEL, NOT A SENTENCE OF OURS.** It is what they
     * called the link on their own settings screen, and this is going out under
     * their name on their own listing. Composing "You can book here:" would be
     * the platform putting words in a business's mouth in the one place `29`'s
     * "AI never writes a review" boundary is watched hardest — and the owner can
     * edit every character of it in the composer before approving anyway.
     */
    public function forReviewReply(): ?string
    {
        $link = $this->usable();

        if ($link === null) {
            return null;
        }

        $label = trim($link->label);

        return $label === ''
            ? $link->destination()
            : $label.': '.$link->destination();
    }

    /**
     * The booking link when there is one and it is safe to put in an `href`.
     *
     * ⛔ **THE SCHEME IS CHECKED HERE BECAUSE THIS IS WHERE THE VALUE REACHES AN
     * `href` ON A PUBLIC PAGE.** The destination is typed by a business into a
     * settings screen, so `javascript:` and `data:` are values a compromised or
     * careless tenant account can store, and Blade's escaping does not stop a
     * scheme — `{{ }}` renders `javascript:alert(1)` into an `href` unchanged and
     * the browser runs it on click, for every customer of that business.
     *
     * ⚠️ **THIS DOES NOT MAKE P7 THE OWNER OF URL VALIDATION.** P6 validates at
     * the write, where a business can be told their link was refused; this is the
     * render-time floor, and it exists because a public surface should not
     * inherit its safety from a screen in another patch. If the two ever
     * disagree, the write is the bug and this is the containment.
     *
     * ⚠️ **AND IT FAILS CLOSED INTO `null`, WHICH R13 ALREADY DEFINES.** An
     * unusable destination and an unset one produce the same surface — no block,
     * no control — rather than a broken link or an error page in front of a
     * customer who has just left feedback.
     */
    private function usable(): ?TenantLink
    {
        $link = $this->registry->booking();

        if ($link === null) {
            return null;
        }

        return $this->isWebAddress($link->destination()) ? $link : null;
    }

    private function isWebAddress(string $destination): bool
    {
        $scheme = parse_url($destination, PHP_URL_SCHEME);
        $host = parse_url($destination, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true)
            && is_string($host)
            && $host !== '';
    }
}
