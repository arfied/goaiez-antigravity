<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Contracts\VoiceProvider;
use App\Enums\OutreachChannel;
use App\Services\Config\DefaultsRegistry;
use App\Support\Identifier;
use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Voice over Infobip's Calls API — the driver behind the R27(a) seam.
 *
 * ⛔ **EVERY ENDPOINT AND EVERY FIELD NAME BELOW WAS READ FROM INFOBIP'S LIVE
 * REFERENCE ON 2026-08-16**, not from memory, not from a tier and not from a
 * summary. `CLAUDE.md` records four occasions where a plausible vendor string
 * was wrong and failed silently (255, 277, 684, 1349) and a fifth in this very
 * vendor's inbound MMS payload (4256–4261, where **both** guessed shapes were
 * wrong). The three reads this driver makes, with their pages:
 *
 *   - **`GET /calls/1/calls/{callId}/history`** —
 *     https://www.infobip.com/docs/api/channels/voice/calls/call-legs/get-call-history
 *     "Get a single call history. Call history retention period is 5 days."
 *     Response: `callId` · `endpoint` · `from` · `to` · `direction` · `state` ·
 *     `startTime` · `answerTime` · `endTime` · `duration` · `ringDuration`.
 *     The live-call endpoint 404s once a call has ended (measured 2026-09-17), and every event this driver acts on fires after the call ends.
 *   - **`GET /calls/1/recordings/calls/{callId}`** —
 *     https://www.infobip.com/docs/api/channels/voice/calls/files-and-recordings/get-call-recordings
 *     Response: `callId` · `endpoint` · `direction` · `files[]` · `status` ·
 *     `reason` · `callsConfigurationId` · `platform` · `startTime` · `endTime`;
 *     each file is `id` · `name` · `fileFormat` · `size` · `creationTime` ·
 *     `duration` · `startTime` · `endTime` · `location` · `sftpUploadStatus` ·
 *     `customData`.
 *   - **`GET /calls/1/recordings/files/{fileId}`** —
 *     https://www.infobip.com/docs/api/channels/voice/calls/files-and-recordings/download-recording-file
 *     Answers `application/octet-stream`.
 *
 * ⚠️ **`/calls/1/recording/file/{fileId}` IS THE WRONG PATH AND IT IS THE ONE A
 * SEARCH RESULT HANDS YOU.** Singular `recording`, singular `file`. The
 * reference page above gives `/calls/1/recordings/files/{fileId}` — plural,
 * plural — and the difference is a 404 that reads like a missing recording. This
 * is `docs/FAILURE-SHAPES.md`'s *"the plausible line is the wrong one"* caught
 * before it shipped rather than after.
 *
 * ⛔ **THERE IS NO METHOD HERE THAT PLACES A CALL, AND WHAT SAYS SO IS THREE
 * LINTS RATHER THAN A RULE.** `29` §2.3 rule 13 was **overridden by an owner
 * ruling on 2026-08-25** (9363); `VoiceTest`'s endpoint arm, `VoiceIngestTest`'s
 * method-set assertion and `OutboundTest`'s permit list are what fail the build,
 * which makes this the best-defended of the sites that used to carry the rule as
 * prose. Infobip's
 * `POST /calls/1/calls` exists and is one line away, and two lints hold it
 * **together rather than separately**: `OutboundTest`'s app-wide `Http::`
 * chokepoint means a call-placing request has to add its file to that permit
 * list by name first, and `tests/Feature/Architecture/VoiceTest.php` then
 * refuses a mutating verb in any **voice** file that can issue an HTTP request
 * at all — `Services/Voice`, `Jobs/Voice`, `Listeners/Voice`,
 * `Http/Controllers/Voice` and `Contracts/VoiceProvider.php`.
 *
 * ⚠️ **"THAT CAN ISSUE ONE" IS PART OF THE CLAIM AND NOT A HEDGE** (4512): a
 * `put` is also a filesystem write, so the scan asks whether the file touches
 * `Http::` or a `PendingRequest` before it reads a verb at all. A voice file
 * that built its own Guzzle client would evade both lints, exactly as it would
 * on every other vendor here — written down rather than glossed.
 *
 * ⚠️ **AND THE VERBS ARE NAMED WITHOUT THEIR ARROW ON PURPOSE**, because
 * `VoiceIngestTest`'s *"the voice seam offers no way to place a call"* reads
 * this file's raw source and does **not** strip comments (4512). Its sibling in
 * `Architecture/VoiceTest` does, for the reason that lint states: naming the
 * hazard is not the hazard.
 *
 * ⚠️ **THIS USED TO SAY "ANY FILE THAT NAMES IT" WHILE THE SCAN WAS
 * `Services/Voice` ALONE** (4513). The containment did hold, through the
 * chokepoint — but a docblock that overclaims enforcement is what stops the next
 * reviewer looking, which is 314–316 exactly. The scan is wider now and this
 * sentence says what it covers.
 *
 * ⛔ **AN `OUTBOUND` LEG IS REFUSED RATHER THAN RECORDED.** The vendor documents
 * `direction` as `INBOUND` | `OUTBOUND`. On an account that can only receive,
 * an `OUTBOUND` value means either somebody built the thing this file forbids or
 * the account is shared with something else — and in both cases writing the row
 * is the wrong answer.
 *
 * ⚠️ **ON LARAVEL'S HTTP CLIENT, NEVER `infobip/infobip-api-php-client`** —
 * `InfobipClient`'s reasoning verbatim: `Http::preventStrayRequests()` and `40`
 * Part 8's outbound lint both see through `Http::`, and neither would see an
 * SDK's bundled Guzzle. It is also what keeps this file inside
 * `outboundHttpPermittedFiles()`.
 *
 * ⚠️ **NO SYNCHRONOUS RETRY.** One attempt, then null, with retrying left to the
 * job that has backoff and jitter.
 *
 * ⛔ **THIS DRIVER IS NOT THE DEFAULT AND CANNOT BE REACHED BY ACCIDENT.**
 * `VOICE_DRIVER` seeds `null` ({@see NullVoiceProvider}); this class is selected
 * only when an operator names it *and* `voice.enabled` is on in the registry
 * *and* Infobip has activated Voice/Calls on the account. Three independent
 * things, deliberately — the driver is a deployment fact, the switch is an
 * operational one, and the activation is somebody else's.
 */
final class InfobipVoiceProvider implements VoiceProvider
{
    /**
     * Infobip's own prefix, verbatim from `Configuration::API_KEY_PREFIX`.
     *
     * ⚠️ **`App`, NOT `Bearer`.** `Http::withToken()` would write `Bearer` and
     * produce a 401 that reads like a bad key, sending whoever is debugging it
     * to rotate a credential that was correct. `InfobipClient` carries the same
     * constant for the same reason; it is duplicated rather than shared because
     * the alternative is one of these two files depending on the other purely
     * for a string.
     */
    private const string AUTH_PREFIX = 'App';

    /**
     * Infobip's registrable domain — `InfobipClient::HOST_SUFFIX`'s argument.
     *
     * ⚠️ **DELIBERATELY NOT `.api.infobip.com`.** The real host is
     * `xxxxx.api-us.infobip.com`: the region is a segment of its own, and both
     * this project's earlier guess (431) and Infobip's own documentation
     * illustration omit it (1593).
     */
    private const string HOST_SUFFIX = '.infobip.com';

    /**
     * Bounded so a vendor that has stopped answering does not hold a worker.
     *
     * Longer than the JSON reads because this one covers the audio download too,
     * and a voicemail is a file rather than an object.
     */
    public const int DOWNLOAD_TIMEOUT_SECONDS = 30;

    public function downloadTimeoutSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('voice.media.download_timeout_seconds');
    }

    /**
     * The ceiling on one recording.
     *
     * ⚠️ **BYTES, AND GENEROUS ON PURPOSE.** What this bounds is memory on a
     * queue worker; a ceiling tighter than a real voicemail would refuse the
     * product's own output and report it as an attack. Infobip renders audio as
     * `.wav`, which is uncompressed, so a two-minute message is already megabytes.
     */
    private const int MAX_RECORDING_BYTES = 25_000_000;

    public function isActivated(): bool
    {
        // ⚠️ **CONFIGURATION, NOT A PROBE.** A live call to the vendor to answer
        // "are we switched on" would put a network round trip behind a screen
        // render and would answer "no" during any outage — which reads to an
        // owner as *your phone stopped working* rather than *we are having a bad
        // minute*. What this reports is that this deployment is configured to
        // reach a voice vendor at all, which is the fact the external gate is
        // about.
        try {
            $key = PlatformCredentials::get('infobip_api_key');
        } catch (RuntimeException) {
            return false;
        }

        $base = config('services.infobip.base_url');

        return trim($key) !== '' && is_string($base) && trim($base) !== '';
    }

    public function call(string $providerCallId): ?VoiceCallFacts
    {
        $body = $this->get('/calls/1/calls/'.rawurlencode($providerCallId).'/history');

        if ($body === null) {
            return null;
        }

        $from = $body['from'] ?? null;
        $to = $body['to'] ?? null;

        if (! is_string($from) || $from === '' || ! is_string($to) || $to === '') {
            // ⚠️ **UNREADABLE IS NULL, AND NULL LEAVES THE ROW `in_progress`.**
            // A call with no numbers cannot be attributed to a tenant, and
            // guessing at either end is how a stranger gets texted.
            VendorLog::failure('infobip_voice', 'GET', '/calls/1/calls/history', 'endpoints_unreadable');

            return null;
        }

        $from = Identifier::normalise($from, OutreachChannel::Sms);
        $to = Identifier::normalise($to, OutreachChannel::Sms);

        if ($from === null || $to === null) {
            VendorLog::failure('infobip_voice', 'GET', '/calls/1/calls/history', 'endpoints_unreadable');

            return null;
        }

        $direction = $body['direction'] ?? null;

        if (is_string($direction) && mb_strtoupper(trim($direction)) === 'OUTBOUND') {
            // See the class docblock. Refused rather than recorded.
            VendorLog::failure('infobip_voice', 'GET', '/calls/1/calls/history', 'outbound_leg_refused');

            return null;
        }

        $state = $body['state'] ?? null;
        $ring = $body['ringDuration'] ?? null;

        return new VoiceCallFacts(
            providerCallId: $providerCallId,
            from: $from,
            to: $to,
            providerState: is_string($state) && $state !== '' ? $state : null,
            startedAt: $this->moment($body['startTime'] ?? null),
            answeredAt: $this->moment($body['answerTime'] ?? null),
            endedAt: $this->moment($body['endTime'] ?? null),
            ringSeconds: is_int($ring) && $ring >= 0 ? $ring : null,
        );
    }

    public function recording(string $providerCallId): ?VoiceRecording
    {
        $body = $this->get('/calls/1/recordings/calls/'.rawurlencode($providerCallId));

        if ($body === null) {
            return null;
        }

        $files = $body['files'] ?? null;

        if (! is_array($files) || $files === []) {
            return null;
        }

        // ⚠️ **THE FIRST FILE, AND ONE VOICEMAIL PER CALL IS THE SCHEMA'S OWN
        // RULE.** `voicemails.call_id` is unique. A call with several recording
        // files is a call that was recorded in segments, which the conditional
        // forwarding flow does not produce; taking the first is the honest
        // simplification, and the alternative — a second table — would be a
        // shape nothing on this path can produce.
        $file = $files[0];

        if (! is_array($file)) {
            return null;
        }

        $id = $file['id'] ?? null;

        if (! is_string($id) || $id === '') {
            VendorLog::failure('infobip_voice', 'GET', '/calls/1/recordings/calls', 'file_id_missing');

            return null;
        }

        $format = $file['fileFormat'] ?? null;
        $seconds = $file['duration'] ?? null;
        $size = $file['size'] ?? null;

        return new VoiceRecording(
            fileId: $id,
            format: is_string($format) && $format !== '' ? mb_strtolower($format) : null,
            seconds: is_int($seconds) && $seconds >= 0 ? $seconds : null,
            bytes: is_int($size) && $size >= 0 ? $size : null,
        );
    }

    public function recordingBytes(string $providerFileId): ?string
    {
        $base = $this->baseUrl();

        if ($base === null) {
            return null;
        }

        $url = $base.'/calls/1/recordings/files/'.rawurlencode($providerFileId);

        $request = $this->request($this->downloadTimeoutSeconds());

        if ($request === null) {
            return null;
        }

        try {
            $response = VendorLog::timed(
                'infobip_voice',
                'GET',
                $url,
                // ⚠️ **REDIRECTS ARE NOT FOLLOWED.** The URL here is built by
                // this application from a documented path on our own account
                // host — unlike `InboundMediaFetcher`'s, which comes out of a
                // payload — but a 302 out of it is still the shape of that host
                // being walked around, and following one costs nothing to refuse.
                fn () => $request->withOptions(['allow_redirects' => false])->get($url),
            );
        } catch (ConnectionException) {
            VendorLog::failure('infobip_voice', 'GET', $url, ConnectionException::class);

            return null;
        }

        if ($response->status() !== 200) {
            // `!== 200` rather than `>= 400` — a 204 and a 302 are both "this is
            // not the recording", and storing the body of a redirect page as a
            // voicemail is worse than storing nothing.
            return null;
        }

        $bytes = $response->body();

        if ($bytes === '' || strlen($bytes) > self::MAX_RECORDING_BYTES) {
            return null;
        }

        return $bytes;
    }

    /**
     * One JSON read, or null for every reason it could not be made.
     *
     * @return array<string, mixed>|null
     */
    private function get(string $path): ?array
    {
        $base = $this->baseUrl();

        if ($base === null) {
            return null;
        }

        $url = $base.$path;

        $request = $this->request((int) config('services.infobip.timeout', 10));

        if ($request === null) {
            return null;
        }

        try {
            $response = VendorLog::timed(
                'infobip_voice',
                'GET',
                $url,
                fn () => $request->acceptJson()->get($url),
            );
        } catch (ConnectionException) {
            VendorLog::failure('infobip_voice', 'GET', $url, ConnectionException::class);

            return null;
        }

        if ($response->failed()) {
            VendorLog::failure('infobip_voice', 'GET', $url, 'http_'.$response->status());

            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    private function request(int $timeout): ?PendingRequest
    {
        try {
            $key = PlatformCredentials::get('infobip_api_key');
        } catch (RuntimeException) {
            // ⚠️ **SWALLOWED, AND THE MESSAGE IS NOT PROPAGATED.** The seam's
            // contract is a null for every reason; a credential exception
            // escaping from here would land on `failed_jobs` beside a payload
            // that names a call.
            return null;
        }

        return Http::withHeaders(['Authorization' => self::AUTH_PREFIX.' '.$key])
            ->timeout($timeout);
    }

    /**
     * The account's own host, refusing to send our API key anywhere else.
     *
     * `InfobipClient::assertInfobipHost()`'s argument, answered as a null rather
     * than as an exception because this class's whole contract is nulls. The
     * leading dot is load-bearing: without it `notinfobip.com` passes.
     */
    private function baseUrl(): ?string
    {
        $base = config('services.infobip.base_url');

        if (! is_string($base) || trim($base) === '') {
            return null;
        }

        $base = rtrim(trim($base), '/');

        $host = parse_url($base, PHP_URL_HOST);

        if (! is_string($host) || ! str_ends_with(mb_strtolower($host), self::HOST_SUFFIX)) {
            return null;
        }

        return $base;
    }

    /**
     * A vendor timestamp, or null when it is not one.
     *
     * ⚠️ **A BAD TIMESTAMP IS A NULL, NEVER `now()`.** Defaulting to the current
     * moment would put a fabricated time on the owner's own call history and, on
     * `answerTime`, would turn every unparseable call into an *answered* one —
     * which is `29` §19.6's gate failing in the direction that texts nobody and
     * tells nobody.
     */
    private function moment(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
