<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Enums\OutreachChannel;

/**
 * The consent wording, and the version that names it.
 *
 * `29` 2 rule 7 requires the wording version stored with every consent record,
 * and ConsentCapture refuses a record without one. That is only worth anything
 * if the version and the words cannot drift apart — so both live here, together,
 * and ConsentDisclosureTest pins each version to its exact literal. Editing the
 * wording without bumping the version fails the build.
 *
 * THE WHOLE PARAGRAPH IS VERSIONED, NOT PART OF IT. `24` 3.2's non-negotiable
 * details include "live links to Terms and Privacy," and that sentence is
 * inside `sms_text`/`email_text` rather than composed separately — a link
 * sentence living outside the versioned string could be edited later with no
 * version bump and no failing test, which is exactly the drift the version
 * exists to prevent. `termsLabel()`/`privacyLabel()` below exist only to supply
 * anchor text for those two phrases where they already appear in the rendered
 * disclosure, never to build the sentence itself.
 *
 * ⛔ THE SMS WORDING IS NO LONGER `24` 3.2 VERBATIM, AND THE DEPARTURE IS
 * DELIBERATE (12280). `24` 3.2 names the tenant as the sender; the platform's
 * 10DLC campaign was REJECTED under code 9999 — *"the consent of the person who
 * will receive the messages must be obtained at the time of opt-in to receive
 * messages directly from the registered brand"* — and the registered brand is
 * GO AI EZ, not the tenant. So the sender of record now leads the sentence and
 * the business follows it. The STOP clause also changed, because Lane A is one
 * shared number and its opt-out has PLATFORM scope, which the old wording never
 * disclosed. ⚠️ COUNSEL HAS NOT REVIEWED THIS TEXT.
 *
 * TWO VERSIONS, NOT ONE. The email wording
 * is ours (see the test) — OURS, NOT A SOURCE DOCUMENT'S, and nobody may later
 * cite it as a required form of words. They will be replaced by counsel at
 * different times and a shared version would force one review to invalidate
 * the other's records.
 *
 * VERSIONS ARE APPEND-ONLY IN SPIRIT. Bump the trailing number when the words
 * change; never reuse a version for different text. Thousands of stored rows
 * point at these strings by name. THE ONE EXCEPTION IS PRE-LAUNCH: no consent
 * record has ever been written against these strings, so editing this wording
 * in place — as this file's own history did once — is honest and a version
 * bump would imply a history that does not exist. Once the first production
 * record is written, that exception is gone: any further change to the words
 * requires a new version, never an edit to an existing one.
 */
final class ConsentDisclosure
{
    public const string SMS_VERSION = 'sms-2026-08-29.1';

    public const string EMAIL_VERSION = 'email-2026-08-01.1';

    /**
     * The reviewer's undertaking not to include health information (2079-2081).
     *
     * ⚠️ A THIRD VERSION, DELIBERATELY NOT REACHABLE FROM `versionFor()`. That
     * method takes an `OutreachChannel` because the two versions above are
     * permissions to *contact somebody*, and a channel is what they permit. This
     * one permits nothing of the sort: it is one reviewer agreeing, about one
     * piece of writing, that they have kept health information out of it. Giving
     * it a channel would be the first step toward `review_phi_consents` being
     * read as a sending basis, which it must never be.
     *
     * ⚠️ AND IT IS NOT TCPA WORDING. The two above carry clauses that have sunk
     * real cases and may not be paraphrased; this one is ours, it cites no
     * source document, and nobody may later cite it as a required form of words
     * — the caveat `EMAIL_VERSION` already carries, for a stronger reason.
     *
     * ⛔ **BUMPED FROM `phi-analysis-2026-08-12.1` AT T176 P15's FIX WAVE, AND
     * THE PRE-LAUNCH EXCEPTION ABOVE WAS DELIBERATELY NOT TAKEN** (4465, 4466).
     * This file's own header says an in-place edit is honest while no record has
     * ever been written against these strings, and that is still literally true.
     * It was refused anyway, because the words are not what changed — **the set
     * of processing they authorise is**. The recovery drafter reads a consented
     * comment to write outbound copy the business may send back to the reviewer;
     * the old wording described an inbound read only. Editing in place would
     * have left every row already captured reading as though it covered a
     * purpose that did not exist when it was shown, and — worse — would have
     * left **no mechanism at all** for telling the two apart. The mechanism is
     * the deliverable here, not the sentence: `PhiAnalysisConsent` treats the
     * old version as gating analysis and never drafting, which is a branch that
     * has to exist before the first production record rather than after it.
     */
    public const string PHI_ANALYSIS_VERSION = 'phi-analysis-2026-08-16.1';

    public static function smsLabel(): string
    {
        return self::string(__('feedback.consent.sms_label'));
    }

    public static function smsText(string $businessName): string
    {
        return self::string(__('feedback.consent.sms_text', ['business' => $businessName]));
    }

    public static function emailLabel(): string
    {
        return self::string(__('feedback.consent.email_label'));
    }

    public static function emailText(string $businessName): string
    {
        return self::string(__('feedback.consent.email_text', ['business' => $businessName]));
    }

    public static function phiAnalysisLabel(): string
    {
        return self::string(__('feedback.consent.phi_analysis_label'));
    }

    /**
     * Plain text, not HTML, and it takes no business name.
     *
     * No `phiAnalysisHtml()` sibling: `html()` exists to turn the two anchor
     * phrases inside the TCPA wording into live links, and `24` §3.2 requires
     * those links on a permission to contact somebody. This is not one, the
     * wording carries neither phrase, and adding the sentence purely to have a
     * link would put an unversioned edit target in a versioned string.
     *
     * It names no business because the undertaking is not about a business —
     * a reviewer is agreeing about their own words, and interpolating the
     * practice's name would read as the practice asking, which is the one
     * reading that would make a patient less careful rather than more.
     */
    public static function phiAnalysisText(): string
    {
        return self::string(__('feedback.consent.phi_analysis_text'));
    }

    public static function termsLabel(): string
    {
        return self::string(__('feedback.consent.terms'));
    }

    public static function privacyLabel(): string
    {
        return self::string(__('feedback.consent.privacy'));
    }

    /**
     * The SMS disclosure as HTML, with its two anchor phrases turned into
     * live links.
     *
     * @see html() for why this is safe against a business named "Fair Terms
     *      Auto" or similar.
     */
    public static function smsHtml(string $businessName, string $termsUrl, string $privacyUrl): string
    {
        return self::html('feedback.consent.sms_text', $businessName, $termsUrl, $privacyUrl);
    }

    /**
     * The email disclosure as HTML. See smsHtml().
     */
    public static function emailHtml(string $businessName, string $termsUrl, string $privacyUrl): string
    {
        return self::html('feedback.consent.email_text', $businessName, $termsUrl, $privacyUrl);
    }

    /**
     * The words behind a stored `consent_records.disclosure_version`, or null
     * when this application can no longer produce them.
     *
     * ⚠️ **THIS IS THE ONLY CLASS THAT MAY ANSWER IT, AND THE HEADER ABOVE IS
     * WHY** — the version and the words *"live here, together"*, so a resolver
     * anywhere else would be a second opinion about what somebody agreed to.
     * `29` §2 rule 7 requires producing those words on demand, and `44` §10 asks
     * a per-contact export for *"verbatim notice snapshots"*; this is what makes
     * that a lookup rather than a copy of the wording in a CSV writer.
     *
     * ⚠️ **NULL IS A REAL AND ORDINARY ANSWER, NOT A FAILURE.**
     * `disclosure_version` is a free-form string and four other surfaces stamp
     * their own — an import attestation's statement version, the review-rules
     * acknowledgement, the auto-renewal notice, the PHI-analysis undertaking —
     * and none of those is a permission to contact somebody, so none of them is
     * here. A caller that renders a placeholder for a null would be asserting
     * the wording is lost when it is merely somewhere else; the honest thing is
     * to print the version and say where it is not resolvable from.
     *
     * ⚠️ **AND IT IS NULL FOR A SUPERSEDED VERSION, WHICH IS THE CASE THAT WILL
     * MATTER.** This class holds the *current* pair per channel. A record
     * stamped `sms-2026-08-01.1` after the wording has moved to `.2` resolves to
     * nothing here rather than to today's words — which is the safe direction by
     * a distance: returning current wording for a superseded version is a
     * document that states, in evidence, that somebody agreed to a sentence they
     * were never shown. `legal_documents` is the store that versions text and
     * `LegalDocuments` is its only reader; wiring the two together is owed and
     * is not guessed at here.
     */
    public static function textForVersion(string $version, string $businessName): ?string
    {
        if ($version === self::SMS_VERSION) {
            return self::smsText($businessName);
        }
        if ($version === self::EMAIL_VERSION) {
            return self::emailText($businessName);
        }

        return null;
    }

    /**
     * The version to stamp on a record for this channel.
     *
     * WhatsApp is not a separate case: it rides the same number and the same
     * disclosure as SMS, so it takes the SMS version rather than needing one of
     * its own. A match without a default is what makes adding a real third case
     * a compile-time conversation rather than a silent fall-through to SMS.
     *
     * ⛔ **`Voice` IS THAT CONVERSATION, AND THE ANSWER IS A REFUSAL — WAVE 39
     * LANE B.** `OutreachChannel::Voice` exists so `ConsentService`'s gate can be
     * asked a truthful question about a call; nothing in this application
     * captures a consent record for one, because no screen offers it and no
     * wording has been written or reviewed for it. Falling through to
     * `self::SMS_VERSION` the way WhatsApp does would be false on its face — the
     * disclosure text is `24` §3.2's *texting* language, "Reply STOP to opt
     * out" among it, stamped onto a record that would claim somebody was shown
     * that sentence before a call. There is also no live caller: this class's
     * only writer path is the feedback-page consent capture, which never
     * receives a channel this platform does not offer. If that ever changes,
     * the wording is owed first and this arm is replaced with it, never edited
     * around it.
     */
    public static function versionFor(OutreachChannel $channel): string
    {
        return match ($channel) {
            OutreachChannel::Sms, OutreachChannel::Whatsapp => self::SMS_VERSION,
            OutreachChannel::Email => self::EMAIL_VERSION,
            OutreachChannel::Voice => throw new \LogicException(
                'No consent disclosure wording exists for Voice yet — nothing may capture '
                .'voice consent until counsel-reviewed wording lands beside a real version.',
            ),
        };
    }

    /**
     * Turn a versioned disclosure into HTML, with its two anchor phrases
     * linked and the business's own name nowhere near the substitution that
     * does it.
     *
     * ORDER IS THE WHOLE SAFETY PROPERTY HERE, NOT THE PATTERN USED TO FIND
     * THE PHRASES. `__()` interpolates `:business` before returning a normal
     * translation, and a `strtr()` over the *interpolated* string would match
     * "Terms" or "Privacy Policy" exactly as readily inside a business's own
     * name as inside the disclosure's wording — a business called "Fair Terms
     * Auto" would get part of its own name turned into a link to
     * `/legal/terms`. So this reads the raw template with `:business` still
     * unresolved, escapes it, links the two phrases while the business name
     * is not in the string at all to be mismatched, and only then substitutes
     * the (separately escaped) name in.
     *
     * NOT GUARANTEED AT RUNTIME THAT BOTH PHRASES ARE STILL THERE — deliberately.
     * An earlier version of this method threw if either phrase was missing,
     * which sounded like the right way to "fail loudly" until a reviewer
     * pointed out where the throw actually ran: `show()` calls this on every
     * `GET /f/{slug}`, a public, unauthenticated route, so a bad edit to
     * `lang/en/feedback.php` would not fail a build — it would 500 that
     * business's entire feedback page in production. `ConsentDisclosureTest`
     * already pins `smsText()`/`emailText()` character for character,
     * including this closing sentence, so the same mistake already fails CI
     * before it ships. Nothing here needs to catch it twice — if a phrase were
     * ever absent, `strtr()` below simply leaves that part of the sentence as
     * plain text and the page renders the disclosure without one link, which
     * is degraded, not dead. The wording is a build-time artefact and belongs
     * to a build-time check; a render is not the place to relitigate it.
     */
    private static function html(string $key, string $businessName, string $termsUrl, string $privacyUrl): string
    {
        $template = e(self::string(__($key)));

        $linked = strtr($template, [
            e(self::termsLabel()) => self::anchor($termsUrl, self::termsLabel()),
            e(self::privacyLabel()) => self::anchor($privacyUrl, self::privacyLabel()),
        ]);

        return str_replace(':business', e($businessName), $linked);
    }

    /**
     * One anchor, built from an already-escaped URL and label.
     *
     * Both arguments are escaped here rather than trusted from the caller:
     * `$label` is always one of this class's own literals, but escaping it
     * anyway costs nothing and means this method has no argument it must
     * assume is already safe.
     */
    private static function anchor(string $url, string $label): string
    {
        return '<a class="underline underline-offset-4" href="'.e($url).'">'.e($label).'</a>';
    }

    /**
     * Narrows __()'s string|array return to string.
     *
     * Every key this class reads is a leaf string in lang/en/feedback.php, never
     * a group, so the array branch is unreachable here — but Larastan level 8
     * cannot know that from the key alone, and an ignore would hide a real
     * mistake (a key typo'd to point at a group) rather than document one that
     * cannot happen.
     *
     * @param  array<array-key, mixed>|string  $value
     */
    private static function string(array|string $value): string
    {
        return is_string($value) ? $value : '';
    }
}
