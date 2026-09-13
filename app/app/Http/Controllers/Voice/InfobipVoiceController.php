<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice;

use App\Enums\PlatformHealthSignal;
use App\Http\Controllers\Controller;
use App\Jobs\Voice\IngestVoiceEventJob;
use App\Services\Ops\PlatformHealth;
use App\Services\Sms\InfobipWebhookVerifier;
use App\Services\Voice\InfobipVoiceEvent;
use App\Services\Voice\VoiceGreeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * `POST /webhooks/infobip/voice` — where a missed call arrives (T176 P2).
 *
 * ⛔ **UNAUTHENTICATED, SO THE SIGNATURE IS THE WHOLE OF THE SECURITY.** A
 * carrier cannot hold a CSRF token, so this route is exempt and HMAC-SHA256 over
 * the raw body replaces it — {@see InfobipWebhookVerifier}, which fails closed
 * when no signing key is configured. `CLAUDE.md`'s rule is that an
 * unauthenticated webhook endpoint is a Blocker; the verification runs **before
 * the body is read as anything but bytes**, because the signature covers the
 * exact bytes Infobip sent and any reformatting invalidates it.
 *
 * ⚠️ **WHAT A FORGERY WOULD BUY IS SMALLER HERE THAN ON THE INBOUND SMS
 * ENDPOINT, AND IT IS STILL NOT NOTHING.** This endpoint accepts a call id and
 * nothing else, and every fact about the call is read back from the vendor under
 * our own credential — so a forged body cannot inject a phone number, a tenant
 * or an outcome. What it *could* do is make this application spend vendor reads,
 * and — for a call id that genuinely exists — replay a missed-call ingest. The
 * settled-outcome check in `VoiceCalls` is what makes the second harmless; the
 * signature is what makes the first impossible.
 *
 * ## Why this is thin, and stays thin
 *
 * Verification is the verifier's, the vendor vocabulary is
 * {@see InfobipVoiceEvent}'s and the work is {@see IngestVoiceEventJob}'s.
 * `InfobipInboundController` established the shape, and what lives here is the
 * HTTP surface — every branch of which is a decision about **whether Infobip
 * should try again**:
 *
 *   401  the signature did not verify, or none is configured. Retrying will not
 *        fix either, and the request may not be from Infobip at all.
 *   422  the payload carried no call id. Same reasoning.
 *   200  queued, or a type we do not act on. Both are finished.
 *
 * ⚠️ **AN EVENT WE DO NOT ACT ON IS A 200, NOT A 422.** Infobip's `EventType`
 * enum has 62 values and this application acts on three; answering an error for
 * the other 59 would invite the carrier to retry every one of them for ever.
 *
 * ⛔ **NOTHING FROM THE REQUEST REACHES THE RESPONSE BODY.** A caller who is not
 * Infobip must learn nothing from the difference between a bad signature, an
 * unconfigured secret and a malformed payload beyond the status itself — in
 * particular whether this endpoint has a signing key yet.
 *
 * ⛔ **AND NOTHING HERE PLACES A CALL, ANSWERS ONE, OR HANGS ONE UP.**
 * `29` §2.3 rule 13 was **overridden on 2026-08-25** (9363), and what refuses a
 * call from this file is `VoiceTest`'s mutating-verb arm, which fails the build
 * on any mutating `Http::` verb in a voice file. This endpoint
 * *observes*; the greeting, the recording and the hang-up are configured on the
 * vendor's own number setup, and {@see VoiceGreeting} is
 * where the unconditional recording announcement is held.
 */
final class InfobipVoiceController extends Controller
{
    public function __invoke(
        Request $request,
        InfobipWebhookVerifier $verifier,
        PlatformHealth $health,
    ): JsonResponse {
        if (! $verifier->verify($request)) {
            $sigHeader = config('services.infobip.signature_header');
            $voiceHeader = 'X-Ib-Hmac-Signature';

            $possible = array_filter(['X-Hub-Signature', 'X-Signature', $sigHeader, $voiceHeader, 'X-Ib-Exchange-Req-Signature', 'X-Ib-Exchange-Req-Timestamp']);
            $present = [];
            foreach ($possible as $h) {
                if ($request->hasHeader($h)) {
                    $present[] = (string) $h;
                }
            }
            $present = array_values(array_unique($present));

            $sigValue = (string) $request->header($voiceHeader, '');
            if ($sigValue === '') {
                $sigValue = is_string($sigHeader) ? (string) $request->header($sigHeader, '') : '';
            }

            Log::warning('Infobip voice webhook refused: signature did not verify', [
                'signature_headers_present' => $present,
                'signature_len' => strlen($sigValue),
                'signature_prefix' => $sigValue !== '' ? substr($sigValue, 0, 7) : null,
                'body_sha256' => hash('sha256', $request->getContent()),
                'content_length' => $request->header('Content-Length'),
                'user_agent' => $request->header('User-Agent'),
            ]);

            // ⚠️ **COUNTED (T176 P23).** An unconfigured signing key refuses
            // every genuine delivery, which here means every missed call is
            // dropped — the owner is never told and the caller is never texted,
            // silently. The count is what makes somebody notice an endpoint
            // doing nothing.
            $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'infobip_voice');

            return response()->json(['error' => 'unverified'], 401);
        }

        // ⚠️ **`json()->all()` IS ALWAYS AN ARRAY**, so there is no "unreadable
        // body" branch to write here — a non-JSON body arrives as an empty one
        // and falls out on the missing call id below. A guard that cannot fail
        // is 256's vacuous lint in a controller.
        $body = $request->json()->all();

        $callId = InfobipVoiceEvent::callId($body);

        if ($callId === null) {
            // ⚠️ **A BODY WITH NO `callId` IS UNUSABLE, NOT MERELY ODD.** It is
            // the only field this endpoint takes, and everything else is read
            // back from the vendor with it.
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        $event = InfobipVoiceEvent::event($body);

        if ($event === null) {
            return response()->json(['handled' => false]);
        }

        // ⚠️ **QUEUED, ALWAYS.** The vendor read-back, the tenant resolution and
        // every retry belong off the path the carrier is waiting on — a webhook
        // that times out is a webhook that gets redelivered, and on this feature
        // a redelivery is a second apology to a member of the public.
        IngestVoiceEventJob::dispatch($callId, $event);

        return response()->json(['handled' => true]);
    }
}
