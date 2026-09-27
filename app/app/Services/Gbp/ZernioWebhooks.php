<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use App\Enums\GbpConnectionStatus;
use App\Models\Location;
use App\Models\ZernioAccountBinding;
use App\Models\ZernioWebhookEvent;
use App\Modules\CWhatsapp\Actions\WhatsappConnectionLookupAction;
use App\Modules\CWhatsapp\Actions\WhatsappDisconnectedExternallyAction;
use App\Modules\X177\Actions\GbpPostSettleAction;
use App\Services\Zernio\ZernioWhatsappInbound;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Handle verified Zernio webhook payloads.
 *
 * Review events share {@see GoogleReviewIngest}. Account disconnect marks the
 * binding's connection disconnected without calling Zernio (they already know).
 * Account **connect** corroborates a binding the redirect already made and
 * never makes one itself — {@see handleAccountConnected()} carries the vendor
 * reasons, which are the whole of why 6615 did not land the way it was raised.
 *
 * ⚠️ **THE EVENT ID IS CLAIMED BEFORE THE PAYLOAD IS PROCESSED, WHICH IS THE
 * VENDOR'S OWN RECOMMENDED PATTERN AND HAS A CONSEQUENCE WORTH KNOWING** (6770).
 * Their overview says to *"insert the event ID into a unique-indexed table …
 * before processing the payload, and skip processing when the insert
 * conflicts"*, and that is what {@see handle()} does. So a delivery that is
 * claimed and then fails is **not recoverable by a redelivery** — the retry
 * reads as a `duplicate`. Nothing here relies on a retry today, because
 * {@see ZernioWebhookController} answers 200 for every outcome and no arm
 * throws; a future arm that wants the vendor's retries must move its claim
 * after its work, and that is a different shape rather than a smaller one.
 */
final class ZernioWebhooks
{
    /**
     * The one platform this application connects.
     *
     * ⚠️ **DELIBERATELY A SECOND COPY OF THE LITERAL RATHER THAN A SHARED
     * CONSTANT.** `ZernioGbpClient::PLATFORM` holds the same string, and
     * `Architecture\GbpTest`'s *"nothing reaches a Google Business client
     * except through the connection store"* fails the build on any file outside
     * its allowlist that so much as **names** that class — a type hint, a
     * container resolve or a static call alike. Widening that allowlist to
     * share a five-word string would trade a chokepoint on the class that reads
     * a tenant's Google listing for the removal of one duplicated literal, and
     * the literal is pinned by tests on both sides.
     *
     * The slug carries no underscore — 1349's trap, re-checked against the raw
     * OpenAPI corpus on 2026-08-21.
     */
    private const string PLATFORM = 'googlebusiness';

    public function __construct(
        private readonly GbpConnections $connections,
        private readonly GoogleReviewIngest $ingest,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return 'handled'|'duplicate'|'ignored'|'unbound'|'confirmed'|'mismatched'
     */
    public function handle(array $payload): string
    {
        $eventId = $payload['id'] ?? null;
        $event = $payload['event'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($event) || $event === '') {
            return 'ignored';
        }

        // insertOrIgnore — StripeWebhooks' pattern. A caught unique violation
        // still aborts the ambient RefreshDatabase transaction on Postgres
        // (25P02), so the check-then-insert that holds only sequentially is the
        // wrong shape here too.
        $claimed = ZernioWebhookEvent::query()->insertOrIgnore([
            'event_id' => $eventId,
            'event_type' => $event,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($claimed === 0) {
            return 'duplicate';
        }

        if ($event === 'review.new' || $event === 'review.updated') {
            return $this->handleReview($payload);
        }
        if ($event === 'account.disconnected') {
            return $this->handleAccountDisconnected($payload);
        }
        if ($event === 'account.connected') {
            return $this->handleAccountConnected($payload);
        }
        if ($event === 'post.platform.published' || $event === 'post.platform.failed') {
            return $this->handlePostPlatform($payload, $event === 'post.platform.published');
        }
        if ($event === 'message.received') {
            return app(ZernioWhatsappInbound::class)->handle($payload);
        }

        return 'ignored';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'handled'|'unbound'|'ignored'|string
     */
    private function handlePostPlatform(array $payload, bool $published): string
    {
        if (($payload['platform']['name'] ?? null) !== 'googlebusiness') {
            return 'ignored';
        }

        $gbpPostId = $payload['post']['metadata']['gbp_post_id'] ?? null;
        if (! is_numeric($gbpPostId)) {
            return 'ignored';
        }
        $gbpPostId = (int) $gbpPostId;

        $accountRef = $this->accountRefFrom($payload);
        if ($accountRef === null) {
            return 'ignored';
        }

        $binding = $this->connections->bindingForAccount($accountRef);
        if ($binding === null) {
            return 'unbound';
        }

        return Tenancy::actingAs($binding->business_id, function () use ($binding, $gbpPostId, $published, $payload): string {
            $result = app(GbpPostSettleAction::class)->settle(
                $binding->business_id,
                $gbpPostId,
                $published,
                is_string($payload['platform']['error'] ?? null) ? $payload['platform']['error'] : null,
                is_string($payload['post']['_id'] ?? null) ? $payload['post']['_id'] : null
            );

            return $result === 'settled' ? 'handled' : $result;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'handled'|'unbound'|'ignored'
     */
    private function handleReview(array $payload): string
    {
        $accountRef = $this->accountRefFrom($payload);

        if ($accountRef === null) {
            return 'ignored';
        }

        $reviewPayload = $payload['review'] ?? null;

        if (! is_array($reviewPayload)) {
            return 'ignored';
        }

        $gbpReview = GbpReview::fromZernio($reviewPayload);

        if ($gbpReview === null) {
            return 'ignored';
        }

        $binding = $this->connections->bindingForAccount($accountRef);

        if ($binding === null) {
            return 'unbound';
        }

        $observedAt = $this->observedAtFrom($payload);

        return Tenancy::actingAs($binding->business_id, function () use ($binding, $gbpReview, $observedAt): string {
            $location = Location::query()->find($binding->location_id);

            if ($location === null) {
                return 'unbound';
            }

            $this->ingest->upsertOne($location, $gbpReview, $observedAt);

            return 'handled';
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'handled'|'unbound'|'ignored'
     */
    private function handleAccountDisconnected(array $payload): string
    {
        $accountRef = $this->accountRefFrom($payload);

        if ($accountRef === null) {
            return 'ignored';
        }

        $binding = $this->connections->bindingForAccount($accountRef);

        if ($binding === null) {
            $waBinding = ZernioAccountBinding::where('account_ref', $accountRef)->first();

            if ($waBinding !== null && $waBinding->platform === 'whatsapp') {
                $businesses = $this->connections->businessesForProfiles([$waBinding->profile_ref]);
                $businessId = $businesses[$waBinding->profile_ref] ?? null;

                if ($businessId !== null) {
                    return Tenancy::actingAs($businessId, function () use ($accountRef): string {
                        $row = app(WhatsappConnectionLookupAction::class)->forAccount($accountRef);

                        if ($row !== null) {
                            app(WhatsappDisconnectedExternallyAction::class)->handle($row);

                            return 'handled';
                        }

                        return 'unbound';
                    });
                }
            }

            return 'unbound';
        }

        return Tenancy::actingAs($binding->business_id, function () use ($binding, $accountRef): string {
            $location = Location::query()->find($binding->location_id);

            if ($location === null) {
                return 'unbound';
            }

            $connection = $this->connections->forLocation($location);

            if ($connection === null || $connection->account_ref !== $accountRef) {
                return 'unbound';
            }

            if ($connection->status === GbpConnectionStatus::Disconnected) {
                return 'handled';
            }

            $this->connections->recordExternalDisconnect($connection, 'system:zernio_webhook');

            return 'handled';
        });
    }

    /**
     * `account.connected` — the vendor's own account-to-profile mapping.
     *
     * ⛔ **IT CORROBORATES A BINDING AND NEVER ORIGINATES ONE, AND THAT IS A
     * FINDING RATHER THAN A SHORTCUT** (6615, closed at 6762–6766 on the terms
     * below rather than the terms it was raised on). 6615 called binding from
     * this webhook *"strictly stronger than 6603, which still lets the browser
     * propose an account"*. It is stronger on authenticity and **weaker on
     * specificity**, and the second is fatal to originating:
     *
     *   ⛔ **the payload cannot name a location.** Read from the raw
     *      `docs.zernio.com/llms-full.txt` corpus on **2026-08-21**, the
     *      payload is `id`, `event`, `timestamp` and an `account` object of
     *      `accountId`, `profileId`, `platform`, `username`, `displayName` —
     *      nothing of ours. `GET /v1/connect/{platform}` accepts `profileId`,
     *      `redirect_url`, `headless` and `loginMethod` and **no state
     *      passthrough**; the only `state` parameter in the whole corpus that a
     *      caller may set belongs to `POST /v1/connect/openai-ads/credentials`.
     *   ⛔ **and a profile is a tenant, not a location.**
     *      `GbpConnections::profileRefFor()` reuses the first stored profile
     *      reference for every location a business has, which matches Zernio's
     *      own multi-tenant guide calling a profile *"the tenant boundary"*.
     *      So a business with three locations connecting has three `pending`
     *      rows on **one** `profileId`, and this event cannot say which.
     *
     * ⚠️ **AND IT MUST NOT BE THE THING THAT COMPLETES A CONNECTION, ON
     * DELIVERY GROUNDS ALONE.** Their webhooks overview (same fetch) documents
     * at-least-once delivery over **7 attempts** on a backoff of 10s, 1m40s,
     * 16m40s, 2h46m40s then 24h capped — ~51 hours end to end — after which the
     * event is **dead-lettered and never retried**. An owner sitting in front
     * of a screen cannot be told *"connected"* on that schedule, and a
     * dead-lettered delivery would mean an owner who granted `business.manage`
     * at Google and never connects at all. The redirect is synchronous and the
     * owner is present; {@see GbpConnections::complete()} asks the vendor the
     * ownership question directly (6603) rather than waiting to be told.
     *
     * ## The three orderings, each with an outcome rather than an absence
     *
     *   redirect then webhook  the ordinary case. The binding exists, the
     *                          profile agrees, and the agreement is recorded:
     *                          `confirmed`.
     *   webhook then redirect  `unbound`. **Nothing is written and nothing is
     *                          blocked** — the redirect that follows completes
     *                          exactly as it would have. This is the arm the
     *                          location ambiguity above forbids acting on.
     *   redirect, no webhook   nothing happens, and nothing must. The
     *                          connection is complete and correct because
     *                          `profileOwnsAccount()` already asked. **The
     *                          absence of this event degrades nothing**, which
     *                          is the property that lets it be best-effort.
     *
     * ⛔ **THE TENANT IS ESTABLISHED FROM OUR OWN INDEX AND NEVER FROM THE
     * PAYLOAD.** `bindingForAccount()` is a platform read keyed on
     * `account_ref`; the payload's `profileId` is only ever **compared** with
     * what that lookup found. A signed event naming another tenant's profile
     * therefore cannot move, create or re-point anything — it produces a
     * `mismatched` record in the tenancy that currently holds the account.
     * ⚠️ **`gbp_account_bindings` has no tenancy trait and no row-level
     * security** (6609), so for this table that comparison is the only layer
     * there is; neither RLS nor a global scope stands behind it.
     *
     * @param  array<string, mixed>  $payload
     * @return 'confirmed'|'mismatched'|'unbound'|'ignored'
     */
    private function handleAccountConnected(array $payload): string
    {
        $accountRef = $this->accountRefFrom($payload);
        $profileRef = $this->profileRefFrom($payload);

        if ($accountRef === null || $profileRef === null) {
            return 'ignored';
        }

        // ⚠️ **THE TEAM IS WIDER THAN THIS APPLICATION.** One Zernio team holds
        // every profile and every platform, and this endpoint is configured per
        // team rather than per profile — their own words. An event for a
        // platform we never connect is not ours to act on, and letting one
        // through would put a non-Google account reference into a comparison
        // that decides whose Google listing we read.
        if (! $this->isGoogleBusiness($payload)) {
            return 'ignored';
        }

        $binding = $this->connections->bindingForAccount($accountRef);

        if ($binding === null) {
            // ⚠️ **THE WEBHOOK-FIRST ARM, AND IT IS INDISTINGUISHABLE FROM A
            // GENUINELY FOREIGN ACCOUNT — SAYING SO IS THE HONEST THING.** An
            // account connected from Zernio's own dashboard, one belonging to a
            // profile this application never made, and one whose redirect has
            // simply not landed yet all arrive here identically. The first two
            // must write nothing; the third cannot be acted on for the location
            // reason above. So all three are `unbound`.
            return 'unbound';
        }

        return Tenancy::actingAs($binding->business_id, function () use ($binding, $accountRef, $profileRef): string {
            $location = Location::query()->find($binding->location_id);

            if ($location === null) {
                return 'unbound';
            }

            $connection = $this->connections->forLocation($location);

            if ($connection === null || $connection->account_ref !== $accountRef) {
                return 'unbound';
            }

            return $this->connections->recordProviderConfirmation($connection, $accountRef, $profileRef);
        });
    }

    /**
     * When Zernio says it built this event — never when we received it.
     *
     * ⛔ **THE DIFFERENCE IS UP TO ROUGHLY 51 HOURS AND IT DECIDES WHETHER A
     * REPLY IS DESCRIBED AS PUBLISHED** (7125). Their webhooks overview
     * documents at-least-once delivery over 7 attempts on a 10s / 1m40s /
     * 16m40s / 2h46m40s / 24h / 24h backoff, and their payload documents
     * `timestamp` as *"UTC time at which Zernio generated this event (set once
     * when the event payload is built, before delivery is queued). Retries and
     * redeliveries keep the original value, so it reflects the event, not the
     * delivery attempt."* (raw `docs.zernio.com/llms-full.txt`, fetched
     * 2026-08-21). So a `review.updated` arriving now may describe a review as
     * it stood two days ago, and
     * `ReviewReplies::reconcileUnconfirmedPublication()` settles an attempt only
     * against a report that post-dates it.
     *
     * ⚠️ **AN UNREADABLE TIMESTAMP IS NULL AND NULL SETTLES NOTHING.** The
     * review is still ingested — a reply we cannot date is no reason to lose a
     * review — and only the reconciliation is skipped.
     *
     * @param  array<string, mixed>  $payload
     */
    private function observedAtFrom(array $payload): ?CarbonInterface
    {
        $timestamp = $payload['timestamp'] ?? null;

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The profile this account belongs to, per the payload.
     *
     * ⚠️ **`profileId` IS DECLARED REQUIRED ON `account.connected` AND THIS
     * STILL FAILS CLOSED ON ITS ABSENCE.** 6605's lesson is that a documented
     * parameter set is a moving contract — the redirect's gained a member on
     * **August 4, 2026**, verified against the raw changelog page on
     * 2026-08-21 — so a missing value is `ignored` rather than treated as a
     * match. 256's shape is a comparison that a missing parameter satisfies
     * vacuously, and that is exactly the defect 6491 was.
     *
     * @param  array<string, mixed>  $payload
     */
    private function profileRefFrom(array $payload): ?string
    {
        $account = $payload['account'] ?? null;

        if (! is_array($account)) {
            return null;
        }

        $ref = $account['profileId'] ?? null;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    /**
     * ⚠️ **ABSENT IS NOT GOOGLE.** `platform` is a required field on this
     * payload, so a payload without one is not a payload we understand, and
     * defaulting an unreadable value to the platform we act on is how a
     * comparison stops being one.
     *
     * @param  array<string, mixed>  $payload
     */
    private function isGoogleBusiness(array $payload): bool
    {
        $account = $payload['account'] ?? null;

        if (! is_array($account)) {
            return false;
        }

        return ($account['platform'] ?? null) === self::PLATFORM;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function accountRefFrom(array $payload): ?string
    {
        $account = $payload['account'] ?? null;

        if (! is_array($account)) {
            return null;
        }

        $ref = $account['id'] ?? $account['_id'] ?? $account['accountId'] ?? null;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }
}
