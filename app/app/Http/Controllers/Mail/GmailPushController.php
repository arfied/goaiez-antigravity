<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mail;

use App\Enums\PlatformHealthSignal;
use App\Enums\WebhookVerification;
use App\Http\Controllers\Controller;
use App\Jobs\IngestGmailPushJob;
use App\Services\Mail\GmailInbox;
use App\Services\Mail\GooglePushTokenVerifier;
use App\Services\Ops\PlatformHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * `POST /webhooks/gmail` — where a customer's reply first becomes known to us.
 *
 * ⛔ **BEFORE THIS FILE EXISTED, `MailReplyRouter` HAD EXACTLY ONE CALLER AND IT
 * WAS DORMANT.** `SesFeedbackController` is the SNS endpoint; `2438` records
 * that `platform_mail.sns.topic_arns` seeds empty, so it accepts nothing, and
 * `2093` makes Gmail the live transport. The arithmetic of those two facts is
 * that on the transport actually carrying mail, **an inbound reply reached
 * nothing at all** — T137 §3 rail 3 names "Gmail push" as a required route and
 * `app/Http/Controllers/Mail/` held one file.
 *
 * ## Three vendors, three authentication shapes, and this is the third
 *
 * ⚠️ **THE BODY IS NOT SIGNED AND NOTHING IN IT IS TRUSTED.** Infobip HMACs the
 * raw body; SNS signs a canonical field list. Pub/Sub signs **neither** — it
 * attaches an OpenID Connect JWT in the `Authorization` header naming the
 * service account that is calling (`cloud.google.com/pubsub/docs/push`, read
 * 2026-08-12). So a verified request here means *"Google's push service, on our
 * subscription, called this URL"* and says nothing whatever about the payload.
 *
 * ⛔ **THE DESIGN THAT MAKES THAT SAFE IS THAT THE PAYLOAD IS NOT ACTED ON.**
 * The only field taken from it is `emailAddress`, which is compared against the
 * mailbox we watch and otherwise discarded. Everything else — which messages
 * arrived, who they were addressed to — is read back from Gmail over our own
 * authenticated connection by {@see IngestGmailPushJob}. A forged body
 * behind a stolen token can therefore make this application look at its own
 * mailbox and nothing more.
 *
 * ## Per-tenant routing, which is not this file's to do and is named here anyway
 *
 * ⚠️ **A REPLY ARRIVES NAMING NO TENANT**, because one relay mailbox serves every
 * client (2097). The tenant comes from the eight-character code in the address
 * the reply was sent *to*, which `MailReplyRouter` resolves and then acts under
 * — and a forged or stale code selects a tenant whose rows do not contain that
 * message, so it finds nothing. That mechanism is `MailReplyRouter`'s and is
 * deliberately not duplicated here.
 *
 * ## Every branch is a decision about whether Pub/Sub should try again
 *
 * The vendor's own table: `102, 200, 201, 202, 204` acknowledge, *"any other
 * status code"* is a nack and the notification is redelivered.
 *
 *   401  unverified — a bad token, an unknown service account, an audience that
 *        is not ours, or no configuration at all. Retrying will not fix any of
 *        them, and a nack would have Google retry a forgery for days.
 *   503  ⛔ **UNVERIFI*ABLE* — GOOGLE'S PUBLISHED KEYS COULD NOT BE FETCHED, SO
 *        NOTHING WAS JUDGED** (9380–9394). Every push arriving while that lasts
 *        is a genuine notification about a real mailbox; a 401 acks and throws
 *        it away, and a non-2xx has Pub/Sub redeliver it.
 *   422  the envelope was not readable. Same reasoning.
 *   200  handled, already handled, not our mailbox, or the path is switched off.
 *        All four are finished, and none is improved by another delivery.
 *
 * ⚠️ **NOTHING FROM THE REQUEST REACHES THE RESPONSE BODY**, and in particular a
 * caller learns nothing about whether this endpoint is configured yet — the
 * refusal for an unconfigured install and the refusal for a forgery are one
 * status with one body.
 */
final class GmailPushController extends Controller
{
    /**
     * How long a handled `messageId` is remembered.
     *
     * Pub/Sub's retry window for an unacknowledged message is measured in
     * minutes to hours; a day is comfortably past it and is cheap. ⚠️ **This is
     * the fast layer only** — the durable one is the history cursor, which is
     * what actually holds if the cache is cleared.
     */
    private const int CLAIM_TTL_SECONDS = 86400;

    public function __invoke(
        Request $request,
        GooglePushTokenVerifier $verifier,
        GmailInbox $inbox,
        PlatformHealth $health,
    ): JsonResponse {
        // ⚠️ **BEFORE THE BODY IS READ AS ANYTHING BUT BYTES**, which is the
        // ordering both sibling webhook controllers keep. It buys less here than
        // it does there — the token does not cover the body — but the ordering
        // is what stops the next reader concluding that it does not matter.
        $verification = $verifier->verify($request->header('Authorization'));

        // ⛔ **THREE OUTCOMES, AND TWO OF THEM USED TO BE ONE** (9380–9394).
        // Compared against the one passing case rather than negated, so a fourth
        // outcome would be a loud failure here rather than a silent acceptance.
        if ($verification !== WebhookVerification::Verified) {
            return self::refuse($verification, $health);
        }

        // ⛔ **THE KILL SWITCH, AFTER THE SIGNATURE AND BEFORE ANY WORK.** After,
        // so that an unconfigured deployment cannot learn from the response
        // whether the feature is on; before, so that turning it off stops the
        // path rather than merely stopping half of it.
        if (! $inbox->isEnabled()) {
            return response()->json(['status' => 'ignored']);
        }

        $envelope = $request->json()->all();

        $message = is_array($envelope['message'] ?? null) ? $envelope['message'] : null;

        if ($message === null) {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        // ⚠️ **THE NOTIFICATION IS BASE64 INSIDE THE ENVELOPE — TWO LAYERS**, the
        // same shape `SesFeedbackController` meets with SNS's JSON-string
        // `Message`. `message.data` is *"base64-encoded"* per the push
        // reference, and the decoded object is
        // `{"emailAddress": ..., "historyId": ...}` per the Gmail push guide.
        $data = is_string($message['data'] ?? null) ? base64_decode($message['data'], true) : false;
        $notification = $data === false ? null : json_decode($data, true);

        if (! is_array($notification)) {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        $emailAddress = $notification['emailAddress'] ?? null;
        // ⚠️ **GOOGLE TYPES `historyId` AS A STRING AND SENDS IT AS A JSON
        // NUMBER**, which is exactly the sort of mismatch `CLAUDE.md` records
        // four instances of. Both are accepted and it is normalised to a string
        // here, because a string is what goes back to Google in the query.
        $historyId = $notification['historyId'] ?? null;

        if (! is_string($emailAddress) || $emailAddress === '' || ! (is_string($historyId) || is_int($historyId))) {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        if (! $inbox->watches($emailAddress)) {
            // Not our mailbox. A 200, because there is nothing Google can do
            // about it and redelivering will not change the answer.
            return response()->json(['status' => 'ignored']);
        }

        $messageId = $message['messageId'] ?? $message['message_id'] ?? null;

        if (! is_string($messageId) || $messageId === '') {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        // ⚠️ **`Cache::add` IS AN ATOMIC CLAIM AND `has`-THEN-`put` IS NOT.** Two
        // concurrent redeliveries of one notification both pass a `has()` check
        // and both dispatch. This is the shape `InboundMessages` records for the
        // identical problem on the SMS side.
        if (! Cache::add('platform-mail:gmail:push:'.$messageId, true, self::CLAIM_TTL_SECONDS)) {
            // ⚠️ **A REPLAY IS A 200, NOT A 409.** Google retried because it was
            // not sure we got the first one; answering with an error status
            // invites it to keep trying — `InfobipInboundController`'s own line.
            return response()->json(['status' => 'duplicate']);
        }

        IngestGmailPushJob::dispatch($emailAddress, (string) $historyId);

        return response()->json(['status' => 'accepted']);
    }

    /**
     * What is counted, and what Pub/Sub is asked to do next, when the request
     * did not pass.
     *
     * ⚠️ **THE SIGNAL IS NAMED FOR SIGNATURES AND THIS IS A TOKEN**, which is
     * the honest thing to record rather than a reason for another counter: what
     * is counted across every webhook endpoint is *requests we refused because we
     * could not establish who sent them*, and an operator paged about it goes
     * and looks at the same kind of credential either way.
     *
     * ⛔ **WHAT IS **NOT** THAT IS A REQUEST NOBODY JUDGED** (9380–9394).
     * {@see GooglePushTokenVerifier} fetches Google's published keys before it
     * can check anything, and until this arm existed an unreachable key host
     * left here as an unverifiable signature — so the bell an operator got said
     * *"the usual cause is a missing or rotated signing secret"* about a fault
     * no secret of ours is involved in. **Every push discarded during that hour
     * was a genuine notification about a real mailbox.**
     *
     * ⚠️ **AND THE `401` IS THE OTHER HALF OF THE LOSS.** This controller's own
     * rule is that a nack would have Google retry a forgery for days — right
     * about a forgery, and a decision to throw away a message this platform
     * never looked at. A `503` has Pub/Sub redeliver inside its retention
     * window, which is the difference between a delayed reply and a lost one.
     *
     * ⚠️ **NOTHING FROM THE REQUEST REACHES THE RESPONSE BODY ON EITHER ARM**,
     * so a caller still cannot learn whether this endpoint is configured.
     */
    private static function refuse(
        WebhookVerification $verification,
        PlatformHealth $health,
    ): JsonResponse {
        if ($verification === WebhookVerification::KeysUnavailable) {
            $health->recordFailure(PlatformHealthSignal::WebhookKeyUnavailable, 'gmail');

            return response()->json(['error' => 'unverifiable'], 503);
        }

        // Counted (T176 P23).
        $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'gmail');

        return response()->json(['error' => 'unverified'], 401);
    }
}
