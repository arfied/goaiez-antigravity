<?php

declare(strict_types=1);

namespace App\Services\Messaging\Composer;

use App\Enums\SmsEncoding;
use App\Exceptions\MessageCannotBeComposed;
use App\Services\Config\DefaultsRegistry;

/**
 * `react.composer` — the one place a reactivation text is assembled, and the
 * only place the ≤159 law is enforced.
 *
 * T137 `SL-2`: *"`react.composer` ≤159 law + `name.normalizer`"*. The T89
 * addendum is the wording of the law itself: *"AI constructs the message, single
 * segment, link included — GSM-7 classes ≤159 chars; UCS-2-forced classes
 * ≤67–70. Composer detects encoding post-composition, re-targets."*
 *
 * ## 159, and the 140 in doc `43`
 *
 * ⚠️ **TWO NUMBERS ARE ON THE RECORD AND THIS BUILDS 159.** Doc `43` is *"the
 * review-invite scheduler, rate governor and 140-character composer"*, and
 * `ReviewInviteSender::compose()` already cites its 140 in prose. `43` **is not
 * buildable**: it extends doc `42`, which was never delivered, so its I9–I18 sit
 * on an I1–I8 set this repository does not have — and one of its other rulings
 * (*"Ops screens Filament v4"*) is already un-adopted by decision 202. T137 is
 * later, it is the build order actually being executed, and its `SL-2` says 159.
 * **They are also not the same message**: `43`'s 140 is a review invite, this is
 * a marketing reactivation, and the two carry different mandatory tails. The
 * conflict is recorded rather than left for the next reader to find twice.
 *
 * ## What this composer refuses to do
 *
 * ⛔ **IT DOES NOT CALL A MODEL, AND THAT IS A REFUSAL RATHER THAN AN
 * OVERSIGHT.** The addendum's *"AI constructs the message"* needs two things
 * that were staged to a lane outside this repository and never delivered: `S6`,
 * the REACT-1 content set, and `S7`, this tier's own guardrails and eval notes.
 * Writing the prompt here would mean inventing the marketing copy that goes out
 * over the **GOAIEZ** 10DLC brand, from our own number pool, to a list whose
 * only sending basis is a tenant's attestation (decisions 2098–2102) — which is
 * the one category of guess this codebase has a rule against. So the wording
 * arrives as a template the tenant authored, and **the law is enforced on
 * whatever body it is handed, no matter who wrote it**: when the AI tier lands,
 * it composes and this still measures.
 *
 * ⛔ **IT DOES NOT MINT THE LINK.** The short-link redirector is `SL-5` and is
 * not this lane's. What this does is *check* the link, because the check is what
 * the 159 budget depends on: an unshortened destination URL does not fit, a link
 * on an unowned host is a false statement to the carrier against a registered
 * 10DLC attribute, and a second link is the shape carriers filter.
 *
 * ⛔ **AND IT NEVER TRUNCATES.** {@see MessageCannotBeComposed} argues that at
 * length. The short version: the first thing a length limit cuts is the tail,
 * and the tail is the opt-out.
 */
final class ReactComposer
{
    /**
     * What `{name}` becomes when there is no name to use.
     *
     * ⚠️ **THE COMPOSER'S WORD, NOT THE NORMALISER'S.** `NameNormaliser` answers
     * null for a contact it cannot greet and must keep answering null — it is
     * also read by the MMS overlay, where "there" painted onto a photograph
     * would be absurd. Substituting here keeps the two facts apart: *we do not
     * know their name* and *this sentence still has to read like English*.
     *
     * ⚠️ **AND IT IS NOT ON `NameNormaliser::NOT_A_NAME`.** It would be wrong
     * there: that list is the words that arrive **in the data** pretending to be
     * names. This one is chosen deliberately, and a contact actually called
     * "There" is not a case worth designing for.
     */
    private const string NAMELESS = 'there';

    /**
     * The sentence carriers require and a length limit eats first.
     */
    private const string OPT_OUT = 'Reply STOP to opt out.';

    /**
     * ⛔ **UNCONDITIONAL HERE, AND `ReviewInviteSender::compose()` MAKES IT
     * CONDITIONAL ON THE LANE — THE DIVERGENCE IS DELIBERATE.** That method
     * omits the disclosure on `MessagingLane::Tenant`, on the sound premise that
     * Lane B rides *the tenant's own* TCR brand and *the tenant's own* number,
     * where naming us would be a false statement about who registered it.
     *
     * **Tenant dedicated number allocation makes that premise false for this lane.** Reactivation sends go
     * out over the **GOAIEZ** brand from **our** pool whatever the consent basis
     * is — that is decision 2101 in one sentence: Lane A infrastructure carrying
     * Lane B consent, with the complaint rate accruing to the platform across
     * every tenant at once. A message that really did leave our brand and does
     * not say so is the false statement, pointing the other way.
     *
     * ⚠️ **THIS DOES NOT SILENTLY CHANGE THE REVIEW-INVITE PATH.** That is
     * another lane's file and another lane's ruling to make; it is reported
     * rather than edited from here.
     */
    private const string DISCLOSURE = 'Sent via GO AI EZ.';

    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly NameNormaliser $names,
        private readonly SmsBudget $budget,
    ) {}

    /**
     * Assemble one reactivation message, or refuse to.
     *
     * @param  string  $template  The tenant's wording. May contain `{name}` and
     *                            `{link}`; nothing else is substituted.
     * @param  string  $businessName  Who the message is from. Required by `24`
     *                                §3.2 and by every carrier's own rules — a
     *                                text naming nobody is filtered as well as
     *                                unlawful.
     * @param  ?string  $contactName  `customers.name` raw. Normalised here.
     * @param  ?string  $link  A short link already minted by `SL-5`, or null.
     *
     * @throws MessageCannotBeComposed
     */
    public function compose(
        string $template,
        string $businessName,
        ?string $contactName = null,
        ?string $link = null,
    ): ComposedText {
        $business = $this->businessName($businessName);
        $rendered = $this->render($template, $contactName, $link);

        // ⚠️ **THE ORDER IS THE COMPLIANCE ORDER: WHO IT IS FROM, THE MESSAGE,
        // THEN HOW TO STOP IT.** A recipient who reads one line of an SMS
        // notification has read the sender; a recipient who reads to the end has
        // read the stop path. Putting the disclosure first and the business
        // second would put our name above the name of the business they actually
        // know, on a message sent on that business's behalf.
        $body = $business.': '.$rendered."\n".self::DISCLOSURE.' '.self::OPT_OUT;

        return $this->measured($body);
    }

    /**
     * Substitute the two placeholders, and refuse every way of getting it wrong.
     *
     * @throws MessageCannotBeComposed
     */
    private function render(string $template, ?string $contactName, ?string $link): string
    {
        $template = trim($template);

        if ($template === '') {
            throw MessageCannotBeComposed::because(
                'A campaign with an empty message template has nothing to say. An empty body still '
                .'costs a segment, still arrives, and still counts against the brand throughput.'
            );
        }

        $hasLinkSlot = str_contains($template, '{link}');

        if ($link !== null && ! $hasLinkSlot) {
            throw MessageCannotBeComposed::because(
                'This send was given a link and the template has no {link} in it. Appending it would '
                .'be this class deciding where a tenant\'s call to action goes; dropping it would send '
                .'a reactivation message with nothing to act on, and nothing anywhere would say so.'
            );
        }

        if ($link === null && $hasLinkSlot) {
            throw MessageCannotBeComposed::because(
                'The template has a {link} and no link was minted for this send. The alternative is a '
                .'message with the literal text "{link}" in it, which is what a merge failure looks '
                .'like to the person receiving it.'
            );
        }

        if ($link !== null) {
            $this->assertLinkIsOurs($link);
        }

        $name = $this->names->firstName($contactName);

        $rendered = str_replace(
            ['{name}', '{link}'],
            [$name ?? self::NAMELESS, $link ?? ''],
            $template,
        );

        $this->assertNothingUnsubstituted($rendered);
        $this->assertOneLinkAtMost($rendered, $link);

        return $rendered;
    }

    /**
     * The link must be on the one host we own and registered.
     *
     * ⚠️ **`goaiez.site` IS NOT OWNED** (decision 2116) though several of the
     * 29–32 documents assume it, so a spec naming it is wrong rather than a
     * requirement. `goaiez.ai` is owned, aged, and — at nine characters plus a
     * token — is what makes the 159 budget reachable at all.
     *
     * ⚠️ **A SUBDOMAIN OF THE HOST IS ACCEPTED AND A SUFFIX MATCH IS NOT.**
     * `notgoaiez.ai` ends with the same nine characters and is somebody else's
     * domain; the dot is what makes the difference and it is why this compares a
     * parsed host rather than calling `str_ends_with()`.
     *
     * @throws MessageCannotBeComposed
     */
    private function assertLinkIsOurs(string $link): void
    {
        $domain = (string) $this->registry->value('messaging.short_link_domain');

        // ⚠️ **A SCHEME IS ADDED FOR PARSING AND NEVER FOR SENDING.** A short
        // link in a text is ordinarily written `goaiez.ai/x7k2` with no scheme,
        // which every handset linkifies and which saves eight of the 159 units —
        // and `parse_url()` reads that as a *path*, so a host check that did not
        // allow for it would refuse exactly the shape the budget depends on.
        $parsable = preg_match('~^[a-z][a-z0-9+.-]*://~i', $link) === 1 ? $link : 'https://'.$link;
        $host = parse_url($parsable, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw MessageCannotBeComposed::because(
                'That link has no host this application can read, so nothing can check whose domain '
                .'it is on. A link in an outbound text is checked before it is sent, never after.'
            );
        }

        $host = mb_strtolower($host);

        if ($host !== $domain && ! str_ends_with($host, '.'.$domain)) {
            throw MessageCannotBeComposed::because(
                "A link in an outbound text may only be on {$domain} (decision 2116). This one is on "
                ."{$host}. The 10DLC campaign is registered with \"embedded links: branded domain "
                .'only, no bit.ly", so another host is a false statement to the carrier — and an '
                .'unshortened destination URL does not fit the 159-unit budget in the first place.'
            );
        }
    }

    /**
     * No placeholder survives into a delivered message.
     *
     * ⚠️ **THIS CATCHES THE TYPO, WHICH IS THE CASE THAT ACTUALLY HAPPENS.**
     * `{Name}`, `{first_name}` and `{ link }` are all things a person writing a
     * template types, none of them is substituted, and every one of them is
     * delivered verbatim to a real customer over a registered brand. There is no
     * screen between the template and the handset that would have shown it.
     *
     * @throws MessageCannotBeComposed
     */
    private function assertNothingUnsubstituted(string $rendered): void
    {
        if (preg_match('/\{[^}]*\}/u', $rendered, $matches) === 1) {
            throw MessageCannotBeComposed::because(
                "The template still contains {$matches[0]} after substitution. Only {name} and {link} "
                .'are substituted, spelled exactly like that; anything else is delivered to the '
                .'recipient as typed.'
            );
        }
    }

    /**
     * One link in the whole message, or none.
     *
     * ⚠️ **CARRIER FILTERING IS THE REASON, NOT READABILITY.**
     * `ReviewInviteSender::compose()` records the same rule for the same reason:
     * a multi-link SMS is the shape carriers filter, and *a filtered message
     * fails silently with no error path back to us* — the failure mode this
     * codebase records most often. The extra link gets in through the template,
     * because `NameNormaliser` already refuses one in a name.
     *
     * ⚠️ **THE RENDERED TEMPLATE ONLY, NEVER THE BUSINESS NAME.** A tenant
     * legitimately called `Cars.com Detailing` would otherwise be unable to run
     * a campaign at all, and the refusal would name a link nobody wrote. The
     * free text and the substituted link are what this is guarding, and both are
     * in `$rendered`.
     *
     * ⚠️ **THE ALTERNATIVES ARE ORDERED SO A FULL URL COUNTS ONCE.** Written the
     * other way round, `https://goaiez.ai/x` matches the scheme *and* the
     * host — two hits for one link, and every campaign that spelled its link out
     * in full would be refused for carrying a second one.
     *
     * @throws MessageCannotBeComposed
     */
    private function assertOneLinkAtMost(string $rendered, ?string $link): void
    {
        $found = preg_match_all(
            '~(?:https?://\S+|\bwww\.\S+|\b[a-z0-9-]+\.(?:com|net|org|ai|io|co|site)\b\S*)~iu',
            $rendered,
        );
        $allowed = $link === null ? 0 : 1;

        if ($found > $allowed) {
            throw MessageCannotBeComposed::because(
                "This message carries {$found} things that look like links and the campaign minted "
                ."{$allowed}. A second link in a text is the shape carriers filter, and a filtered "
                .'message fails silently — it is delivered nowhere and reported as sent.'
            );
        }
    }

    /**
     * @throws MessageCannotBeComposed
     */
    private function businessName(string $businessName): string
    {
        $business = trim($businessName);

        if ($business === '') {
            throw MessageCannotBeComposed::because(
                'A reactivation text has to name the business it is from (`24` §3.2). An anonymous '
                .'marketing message is both a carrier-filtering problem and a compliance one.'
            );
        }

        return $business;
    }

    /**
     * Measure the finished body, and refuse it if the law is not met.
     *
     * ⚠️ **MEASURED AFTER ASSEMBLY, WHICH IS WHAT THE LAW ACTUALLY SAYS** — the
     * T89 addendum's *"composer detects encoding post-composition"*. Measuring
     * the template would miss the business name, the disclosure, the opt-out and
     * the link; measuring the parts and adding them up would miss the encoding
     * cliff entirely, because one curly apostrophe anywhere in the assembled
     * body drops the budget from 159 to 67 for the **whole** message.
     *
     * @throws MessageCannotBeComposed
     */
    private function measured(string $body): ComposedText
    {
        $encoding = $this->budget->encodingOf($body);
        $length = $this->budget->lengthOf($body);

        if ($length > $encoding->segmentBudget()) {
            throw MessageCannotBeComposed::because(sprintf(
                'This message is %d %s units and the single-segment law allows %d. %s Shorten the '
                .'template: nothing here truncates, because the first thing a length limit cuts is '
                .'the opt-out sentence at the end.',
                $length,
                $encoding->value,
                $encoding->segmentBudget(),
                $encoding === SmsEncoding::Ucs2
                    ? 'It contains at least one character outside the GSM-7 alphabet — a curly quote, '
                        .'an em dash or an emoji is enough — which more than halves the budget for the '
                        .'whole message rather than for that character.'
                    : '',
            ));
        }

        return new ComposedText($body, $encoding, $length);
    }
}
