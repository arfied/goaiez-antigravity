<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Services\Feedback\ConsentDisclosure;
use App\Services\Sms\ComplianceReplies;
use Illuminate\Contracts\View\View;

/**
 * The call-to-action page the 10DLC campaign registration points at.
 *
 * WHY THIS EXISTS AT ALL. A campaign submitted to TCR through Infobip carries
 * three public URLs: where a consumer opts in, the SMS programme terms, and the
 * privacy policy. A carrier reviewer loads them and checks that the consent
 * language is present, that consent is not a condition of purchase, that message
 * frequency and rates are stated, that STOP and HELP are named, and that the box
 * is not pre-ticked. `/legal/sms-terms` and `/legal/privacy` already existed;
 * this page did not, and 10DLC registration is the longest external clock on the
 * project (2110).
 *
 * ⚠️ THE WORDING IS NOT RETYPED HERE. It is rendered from `ConsentDisclosure`,
 * the same source the hosted feedback page renders at the actual moment of
 * capture, along with the version stamped on every record. That is the whole
 * point of this page being code rather than a static file: a carrier is being
 * shown the disclosure this system actually uses, and a hand-copied paragraph
 * would drift from it silently — `BotController`'s user-agent string is the same
 * shape of promise and is passed in for the same reason.
 *
 * ⚠️ `{Business}` STANDS WHERE A BUSINESS NAME GOES, AND IS NOT A PLACEHOLDER
 * NOBODY FILLED IN. The disclosure names the business the consumer is giving
 * their number to; this page is not about one business, so the token stays
 * visible. It is T137 R3's own notation for the on-behalf-of identity
 * (`{Business} via GOAIEZ`), which is what the message header will read.
 *
 * ⛔ THERE IS NO POST HERE YET, AND THAT IS RECORDED RATHER THAN OVERLOOKED —
 * see decision 2122. `consent_records.business_id` is NOT NULL and the table is
 * `FORCE ROW LEVEL SECURITY`, so every consent record this application can write
 * belongs to a tenant. A platform-level opt-in form has no tenant, and the two
 * ways to give it one — inventing a house business, or writing consent outside
 * `ConsentService` — are respectively an architectural decision that is not this
 * lane's to make and the second consent path CLAUDE.md forbids. What this page
 * does instead is show the live capture surface's own wording and send the
 * reader to it.
 *
 * ⚠️ **AND FROM 3263 IT SHOWS THE STOP CONFIRMATION THE SAME WAY, FOR THE SAME
 * REASON.** This page already promised what a STOP *does* — that it reaches
 * every business on the platform, not only the one that texted. From 3260 there
 * is a text message making that promise back to the person, so the page renders
 * {@see ComplianceReplies::platformStopBody()} rather than retyping it: two
 * places holding one sentence is the shape that stops matching, and this page's
 * own keyword list was driven out of `InboundKeyword::parse()` for exactly that.
 *
 * ⚠️ **THE PLATFORM FORM RATHER THAN A BUSINESS-ATTRIBUTED ONE**, which is the
 * honest choice twice over: this page is not about one business — `{Business}`
 * stays a visible token above for the same reason — and a carrier reviewer who
 * texts STOP to the pool number receives literally this string.
 */
final class SmsOptInController extends Controller
{
    /**
     * The name shown where a business's own name appears in the disclosure.
     *
     * @see self for why a token rather than a name.
     */
    private const string BUSINESS_TOKEN = '{Business}';

    public function __invoke(ComplianceReplies $replies): View
    {
        $termsUrl = route('sms-terms');
        $privacyUrl = route('privacy');

        return view('marketing.sms-optin', [
            'smsLabel' => ConsentDisclosure::smsLabel(),
            'smsText' => ConsentDisclosure::smsHtml(self::BUSINESS_TOKEN, $termsUrl, $privacyUrl),
            'disclosureVersion' => ConsentDisclosure::SMS_VERSION,
            // Read from the sender, never retyped — see the class docblock.
            'stopConfirmation' => $replies->platformStopBody(),
            'termsUrl' => $termsUrl,
            'privacyUrl' => $privacyUrl,
            'termsTitle' => LegalDocumentType::SmsTerms->title(),
            'privacyTitle' => LegalDocumentType::Privacy->title(),
        ]);
    }
}
