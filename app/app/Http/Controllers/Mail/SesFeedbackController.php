<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mail;

use App\Console\Commands\ConfirmSnsSubscription;
use App\Enums\PlatformHealthSignal;
use App\Enums\WebhookVerification;
use App\Http\Controllers\Controller;
use App\Services\Mail\MailFeedback;
use App\Services\Mail\MailReplyRouter;
use App\Services\Mail\SnsMessageVerifier;
use App\Services\Mail\SnsSubscriptions;
use App\Services\Ops\PlatformHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * `POST /webhooks/ses` — the typed bounce and complaint feed (open question H).
 *
 * ⚠️ **THE ENDPOINT EXISTS BEFORE THE TRANSPORT DOES, AND THAT IS THE ORDER
 * 2069 ASKED FOR.** *"The webhook is the deliverable — not the vendor choice …
 * H is not closed until the handler exists."* Nothing arrives here while
 * `MAIL_MAILER` is `gmail`, because Gmail publishes nothing to SNS — which is
 * exactly 2094's finding and is why `MailFeedbackSignal::NdrOnly` refuses
 * customer-facing mail on that transport. The day SES is slotted in
 * (`MAIL_MAILER=smtp`, SES credentials), this is already running.
 *
 * ⚠️ **SNS SENDS `text/plain`, NOT `application/json`**, and this is the detail
 * that makes a from-memory implementation fail on the first delivery.
 * `$request->json()` and `$request->input()` both look at the content type, so
 * they return nothing here. The body is decoded from `getContent()` — which is
 * also the only thing the signature covers.
 *
 * ⚠️ **THE SUBSCRIPTION CONFIRMATION IS NOT AUTO-CONFIRMED.** SNS asks an
 * endpoint to confirm a subscription by fetching a `SubscribeURL`, and this
 * controller fetches nothing: it is verified, held for an operator, logged with
 * the topic named, and confirmed **by a person**.
 *
 * ⛔ **THE PERSON'S HALF DID NOT EXIST UNTIL 2026-08-26, AND THIS DOCBLOCK SAID
 * IT DID** (10220–10229). It read *"confirmed by a person — one console click,
 * once"*. **There is no such click.** The SNS console's *Confirm subscription*
 * action asks for the **token**, and the token exists nowhere but inside the
 * POST body this controller received and — correctly — refused to log. A
 * genuine, signature-verified confirmation arrived on production on that date,
 * was logged with its topic, and left the subscription at `Pending
 * confirmation` with **no supported way to complete it at all**: the safe half
 * was built and the human half was assumed. {@see ConfirmSnsSubscription} is
 * the half that was missing, and {@see SnsSubscriptions} is what holds the
 * capability between the two.
 *
 * ⛔ **AND THE THREAT THE REFUSAL WAS ARGUED AGAINST IS ALREADY CLOSED BY
 * SOMETHING ELSE — MEASURED BY MUTATION, NOT READ** (10220). The sentence above
 * used to continue *"…doing that automatically means anybody who can reach this
 * URL can subscribe it to their own topic and start feeding it suppressions"*.
 * That has not been true since the topic allowlist landed:
 * {@see SnsMessageVerifier::verify()} asks `topicIsAllowed()` **before** `Type`
 * is read, so a `SubscriptionConfirmation` naming a stranger's topic is refused
 * `401` and never reaches the branch below. Their ARN carries **their** AWS
 * account id, it can never match a value we wrote into `SES_SNS_TOPIC_ARNS`,
 * and `TopicArn` is one of the fields AWS's signature covers.
 *
 * ⚠️ **THE REFUSAL IS KEPT ANYWAY, ON A DIFFERENT ARGUMENT, AND THE DIFFERENCE
 * MATTERS.** *"An operator should know a subscription happened"* is not *"an
 * attacker could subscribe us"*: a confirmation for a topic we **do** allow
 * means somebody inside our own AWS account pointed a feed at the endpoint that
 * can suppress any address on this platform, and a person completing it by hand
 * is the only moment anybody notices. **Widening this to an auto-confirm is the
 * owner's call, recommended at 10222 and deliberately not taken here.**
 *
 * Every branch is a decision about whether SNS should try again:
 *
 *   401  unverified — a bad signature, an unknown topic, or no topic allowlist
 *        configured at all. **A decision**, so SNS is told not to bother again.
 *   503  ⛔ **UNVERIFI*ABLE* — WE COULD NOT FETCH THE CERTIFICATE, SO NOTHING
 *        WAS JUDGED** (9380–9394). Answering 401 here discards a message that
 *        may well have been a genuine bounce; a non-2xx has SNS retry it.
 *   422  the body was not readable as an SNS envelope.
 *   200  handled, or deliberately not handled. A delivery event, a transient
 *        bounce and an unknown event type are all finished, and none is
 *        improved by SNS delivering it again.
 */
final class SesFeedbackController extends Controller
{
    public function __invoke(
        Request $request,
        SnsMessageVerifier $verifier,
        MailFeedback $feedback,
        MailReplyRouter $router,
        PlatformHealth $health,
        SnsSubscriptions $subscriptions,
    ): JsonResponse {
        $envelope = json_decode($request->getContent(), true);

        if (! is_array($envelope)) {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        // Before anything is read as meaning rather than bytes.
        $verification = $verifier->verify($envelope);

        // ⛔ **THREE OUTCOMES, AND TWO OF THEM USED TO BE ONE** (9380–9394). A
        // `match` rather than a negated boolean, so that only
        // `WebhookVerification::Verified` continues and a fourth outcome would
        // be an `UnhandledMatchError` here rather than a silent acceptance.
        if ($verification !== WebhookVerification::Verified) {
            return self::refuse($verification, $health);
        }

        $type = $envelope['Type'] ?? null;

        if ($type === 'SubscriptionConfirmation') {
            // ⚠️ THE TOPIC IS NAMED AND THE `SubscribeURL` IS NOT LOGGED. The
            // URL is a single-use capability that confirms the subscription for
            // whoever fetches it; a copy of it in the log is that capability
            // sitting in a file.
            //
            // ⛔ **IT IS HANDED TO A STORE THAT EXPIRES INSTEAD, AND THAT IS NOT
            // THE SAME COMPROMISE.** A log line is permanent, unbounded and
            // read by whoever reads logs; the held value expires on its own
            // after an hour, is dropped the moment it is spent, and is keyed by
            // a topic this deployment already accepts. `record()` fetches
            // nothing — the socket opens only when a person runs
            // `mail:sns-subscriptions <topic>`.
            $topicArn = is_string($envelope['TopicArn'] ?? null) ? $envelope['TopicArn'] : null;
            $subscribeUrl = is_string($envelope['SubscribeURL'] ?? null) ? $envelope['SubscribeURL'] : null;

            $held = $topicArn !== null
                && $subscribeUrl !== null
                && $subscriptions->record($topicArn, $subscribeUrl);

            Log::warning($held
                ? 'An SNS subscription confirmation arrived and is waiting for an operator. '
                    .'Run `php artisan mail:sns-subscriptions` on the server to complete it.'
                : 'An SNS subscription confirmation arrived and could not be held for an operator.', [
                    'topic_arn' => $topicArn,
                ]);

            return response()->json(['status' => 'confirmation required']);
        }

        if ($type !== 'Notification') {
            return response()->json(['status' => 'ignored']);
        }

        // The SES event is a JSON *string* inside the SNS envelope's `Message`
        // field. Two layers, and the signature covers the outer one.
        $event = json_decode(
            is_string($envelope['Message'] ?? null) ? $envelope['Message'] : '',
            true,
        );

        if (! is_array($event)) {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        // ⚠️ **INBOUND MAIL ARRIVES ON THE SAME TOPIC SHAPE AND IS ROUTED HERE
        // RATHER THAN AT A SECOND ENDPOINT**, which is the opposite of the
        // decision `InfobipDeliveryController` records — and the reason is the
        // one that decision gives. Infobip's two payloads are indistinguishable
        // (`results[]` carrying `messageId` on both), so telling them apart
        // needed two URLs. SES's are not: `notificationType` is `Received` and
        // no other event carries it, so there is no guess to get wrong.
        if (($event['notificationType'] ?? null) === 'Received') {
            $destination = $event['mail']['destination'] ?? null;

            $routed = $router->route(is_array($destination) ? array_values($destination) : []);

            return response()->json(['routed' => $routed]);
        }

        // ⛔ **A HEARTBEAT PER EVENT TYPE, WHICH IS WHAT MAKES A SINGLE-TYPE
        // SILENCE VISIBLE RATHER THAN INDISTINGUISHABLE FROM A HEALTHY FEED**
        // (10280–10289). `PlatformHealth::beat()` already exists for exactly
        // this shape — *"nothing reads this to decide anything; its absence is
        // the signal"* — and it is deliberately not read by
        // `PlatformHealthChecks::PROCESSES`, which is a fixed, named list
        // (`scheduler`, `queue`). Adding a source here cannot create a new
        // alert on its own; it only makes `lastBeat()` answerable for a
        // question nothing could answer before.
        //
        // ⚠️ **WHY THIS EXISTS: A DELIVERY-ONLY OUTAGE IS INVISIBLE TO EVERY
        // EXISTING INSTRUMENT.** `SendingRates::trafficWithoutOutcomes()` and
        // `PlatformComplaintRate`'s two silence bells (`DeliveryReceiptsSilent`,
        // `TenantDeliveryReceiptsSilent`) all read `delivered + failed` as one
        // number — proof that *something* came back. An SES identity that stops
        // publishing `Delivery` notifications while `Bounce`/`Complaint` keep
        // arriving (a plausible operator mistake: unchecking the one box that
        // "doesn't suppress anyone anyway") therefore reads as a healthy,
        // reporting platform on every one of those instruments, while the
        // complaint rate's own denominator — `delivered` — quietly stops
        // growing. This is the narrower, quieter case `.env.example`'s
        // "delivery is not optional" warning does not by itself make
        // observable: it explains why delivery matters, not whether it is
        // still arriving.
        //
        // ⛔ **DELIBERATELY NOT AN ALERT.** Turning this into something that
        // pages without being asked belongs beside `PlatformHealthChecks`'s
        // other rangSince()-with-no-threshold checks, and that file's queue
        // sits closer to the messaging/consent lane's territory than to this
        // one — reported rather than reached for. What this call adds is the
        // one thing a bell would need to be written against: a fact this
        // application did not previously record at all. `mail:feedback-status`
        // is the on-demand reader.
        $rawNotificationType = $event['eventType'] ?? $event['notificationType'] ?? null;
        $eventSlugMap = [
            'Bounce' => 'bounce',
            'Complaint' => 'complaint',
            'Delivery' => 'delivery',
        ];
        $eventSlug = $eventSlugMap[$rawNotificationType] ?? 'other';
        $health->beat('ses.notification.'.$eventSlug);

        return response()->json(['suppressed' => $feedback->apply($event)]);
    }

    /**
     * What is counted, and what SNS is asked to do next, when the message did
     * not pass.
     *
     * ⛔ **THE `401` USED TO COVER BOTH ANSWERS AND ONE OF THEM WAS NEVER
     * JUDGED** (9380–9394). A refusal is a decision — bad signature, unknown
     * topic, no allowlist configured — and SNS is told not to bother again, on
     * this controller's own stated rule that a nack has SNS retry a forgery for
     * days. **A key we could not fetch is not a decision**: the message may well
     * have been a genuine bounce, and answering `401` throws it away for good.
     *
     * ⚠️ **`503` RATHER THAN `401`, AND IT IS THE POINT OF THE ARM RATHER THAN
     * A DETAIL.** SNS retries a non-2xx with backoff, so a certificate host that
     * is briefly erroring costs a delayed suppression instead of a lost one.
     *
     * ⛔ **AND THE COUNTER IS THE OTHER HALF: `WebhookSignature` HERE WOULD RING
     * `WebhookSignatureFailures`, WHOSE SUMMARY TELLS AN OPERATOR THE USUAL
     * CAUSE IS OUR OWN ROTATED SIGNING SECRET.** That sentence is right about a
     * refusal and is the wrong remedy, at three in the morning, about AWS.
     *
     * ⚠️ **NOTHING FROM THE REQUEST REACHES THE RESPONSE BODY ON EITHER ARM.**
     * A caller still learns nothing about how this endpoint is configured; what
     * differs is only whether they are asked to try again.
     */
    private static function refuse(
        WebhookVerification $verification,
        PlatformHealth $health,
    ): JsonResponse {
        if ($verification === WebhookVerification::KeysUnavailable) {
            $health->recordFailure(PlatformHealthSignal::WebhookKeyUnavailable, 'ses');

            return response()->json(['error' => 'unverifiable'], 503);
        }

        // Counted (T176 P23). This endpoint is open question H's whole answer:
        // refusing every genuine event means no bounce and no complaint is ever
        // suppressed, and the first evidence would otherwise be a sending
        // reputation nobody can get back.
        $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'ses');

        return response()->json(['error' => 'unverified'], 401);
    }
}
