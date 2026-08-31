<?php

declare(strict_types=1);

namespace App\Services\Messaging\Composer;

use App\Exceptions\MessageCannotBeComposed;
use App\Services\Messaging\ReviewInviteSender;
use App\Support\LegalCanon;
use App\Support\Messaging\ReviewAskCatalog;

/**
 * Assemble one seeded review-ask template, with its footer bound from the L-3
 * registry key — CC-5 §3.
 *
 * ## ⛔ IT IS NOT `ReviewInviteSender`'s COMPOSER AND DOES NOT REPLACE IT
 *
 * {@see ReviewInviteSender::compose()} sends the English ask today, as a fixed
 * sentence pair, and its docblock refuses in as many words to grow into a
 * template engine. Rewriting it to render from a seeded row would be this lane
 * changing the most compliance-sensitive send path in the product against an
 * explicit written refusal on the file — `ReactComposer` faced the identical
 * question about the same method and deferred it (*"another lane's file and
 * another lane's ruling to make"*). **Reported rather than edited from here.**
 *
 * What this class is: the composer the *seeded* templates need, ready for the
 * locale-aware ask path when there is one, and — today — the thing that proves
 * the footer is bound rather than baked.
 *
 * ## ⛔ THE FOOTER IS COMPOSED, NEVER STORED
 *
 * CC-5 §3: *"composed at send-time from the key, not baked into the template, so
 * a counsel swap updates every future send."* {@see LegalCanon::askFooter()}
 * reads `legal.sms_ask_footer` at the moment of composition and **refuses when
 * it is unset** — a review invite with no footer is a message with no stated way
 * to stop it, and there is no conservative default for a legal sentence, only an
 * invented one.
 *
 * ## ⛔ AND A LOCALE THE CANON HAS NO TWIN FOR IS REFUSED
 *
 * `legal.sms_ask_footer` is one key. Appending its wording to a Spanish message
 * would put a compliance sentence in front of somebody in a language they did
 * not choose, and using LP-0's own Spanish suffix instead would ship unreviewed
 * legal text — the thing `lang/en/feedback.php` already refused for this exact
 * audience. So every non-English template composes into a named refusal, and
 * what closes it is one Spanish footer from counsel. See
 * {@see ReviewAskCatalog} for the full argument and decision 5257.
 */
final class ReviewAskComposer
{
    /**
     * The locale `legal.sms_ask_footer` is understood to be written in.
     *
     * ⚠️ **A CONSTANT RATHER THAN A GUESS AT THE STORED STRING'S LANGUAGE.**
     * Detecting the language of a footer would be this class deciding whether
     * counsel's sentence is Spanish enough, which is not a judgement code makes.
     * When a per-locale key exists, this becomes a lookup and the refusal below
     * stops firing.
     */
    private const string CANON_LOCALE = 'en';

    public function __construct(
        private readonly LegalCanon $canon,
        private readonly NameNormaliser $names,
        private readonly SmsBudget $budget,
    ) {}

    /**
     * One review ask, or a refusal.
     *
     * @param  string  $key  A key from {@see ReviewAskCatalog::templates()}.
     * @param  ?string  $contactName  `customers.name` raw. Normalised here.
     *
     * @throws MessageCannotBeComposed
     */
    public function compose(string $key, string $businessName, ?string $contactName, string $link): ComposedText
    {
        $template = $this->template($key);

        if ($template['locale'] !== self::CANON_LOCALE) {
            throw MessageCannotBeComposed::because(sprintf(
                'The ask template %s is written in `%s` and the review-invite footer canon '
                .'(`%s`) is one key with no twin in that language. Sending this would either '
                .'attach an English compliance sentence to a message the recipient chose to read '
                .'in another language, or ship the language pack\'s own unreviewed wording as if '
                .'counsel had approved it. Neither is a send this application makes.',
                $key,
                $template['locale'],
                LegalCanon::ASK_FOOTER_KEY,
            ));
        }

        $body = $this->render($template['body'], $businessName, $contactName, $link);

        // ⚠️ **THE FOOTER IS READ HERE, ONE CALL BEFORE THE MEASUREMENT**, so a
        // counsel edit that lengthens it is caught by the budget rather than
        // silently producing a second segment.
        $composed = $body.' '.$this->canon->askFooter();

        return $this->measured($composed);
    }

    /**
     * @return array{key: string, locale: string, body: string}
     *
     * @throws MessageCannotBeComposed
     */
    private function template(string $key): array
    {
        foreach (ReviewAskCatalog::templates() as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }

        throw MessageCannotBeComposed::because(
            "There is no seeded ask template called `{$key}`. A send that fell back to some other "
            .'template would put a message the caller did not choose in front of a real customer.'
        );
    }

    /**
     * Substitute the three placeholders, and refuse every way of getting it
     * wrong.
     *
     * ⚠️ **THE SLOT-RESOLUTION GUARD, AND IT IS THE SAME CLAIM `ReactComposer`
     * MAKES ONE FILE OVER** (CC-5 §0). Neither delegates to the other: the two
     * substitute different vocabularies, and a shared helper would have to
     * accept both sets, which is how a `{business}` becomes legal in a campaign
     * body that names the business twice.
     *
     * @throws MessageCannotBeComposed
     */
    private function render(string $template, string $businessName, ?string $contactName, string $link): string
    {
        $business = trim($businessName);

        if ($business === '') {
            throw MessageCannotBeComposed::because(
                'A review ask has to name the business it is from (`24` §3.2). An anonymous message '
                .'is both a carrier-filtering problem and a compliance one.'
            );
        }

        $name = $this->names->firstName($contactName);

        if ($name === null && str_contains($template, '{name}')) {
            throw MessageCannotBeComposed::because(
                'The ask template carries {name} and this contact has no usable name. LP-0 authors a '
                .'"sin nombre" variant for exactly this case; substituting a stand-in would produce '
                .'a greeting that is not the language the rest of the message is in.'
            );
        }

        $rendered = str_replace(
            ['{name}', '{business}', '{link}'],
            [$name ?? '', $business, $link],
            $template,
        );

        if (preg_match('/\{[^}]*\}|\[[^\]]*\]/u', $rendered, $matches) === 1) {
            throw MessageCannotBeComposed::because(
                "This ask still contains {$matches[0]} after substitution. Only {name}, {business} "
                .'and {link} are substituted, spelled exactly like that; anything else is delivered '
                .'to the recipient as typed.'
            );
        }

        return $rendered;
    }

    /**
     * Measure the finished body against its locale's ceiling.
     *
     * ⚠️ **THIS REFUSES WHERE `ReviewInviteSender::compose()` DELIBERATELY DOES
     * NOT, AND THE DIFFERENCE IS WHOSE WORDING IT IS.** That method's text is
     * fixed and unshortenable, so refusing would drop an invite a customer was
     * promised over a fraction of a cent. These are seeded templates somebody
     * can edit — `ReactComposer`'s position — and the ceiling is the whole
     * reason LP-0 wrote around four Spanish accents.
     *
     * @throws MessageCannotBeComposed
     */
    private function measured(string $body): ComposedText
    {
        $encoding = $this->budget->encodingOf($body);
        $length = $this->budget->lengthOf($body);

        // ⚠️ **THE CATALOGUE's CEILING AND THE ENCODING's OWN BUDGET, WHICHEVER
        // IS TIGHTER.** They differ on UCS-2 on purpose: `SmsEncoding` answers
        // 67 for one segment, and LP-0 targets 134 — *"= 2 segments … so a
        // reactivation send stays 2 segments, never 3"*. Taking the smaller
        // would refuse every UCS-2 line LP-0 intends to ship; taking the larger
        // blindly would let a GSM-7 line past 159.
        $ceiling = max(
            ReviewAskCatalog::CEILING[$encoding->value],
            $encoding->segmentBudget(),
        );

        if ($length > $ceiling) {
            throw MessageCannotBeComposed::because(sprintf(
                'This ask is %d %s units and the ceiling for that alphabet is %d. Nothing here '
                .'truncates, because the first thing a length limit cuts is the footer at the end.',
                $length,
                $encoding->value,
                $ceiling,
            ));
        }

        return new ComposedText($body, $encoding, $length);
    }
}
