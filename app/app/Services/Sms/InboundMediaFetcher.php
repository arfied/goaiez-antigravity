<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\InboundMediaOutcome;
use App\Jobs\Sms\CaptureInboundMediaJob;
use App\Services\Config\DefaultsRegistry;
use App\Services\Places\ShortLinkResolver;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Fetch one inbound MMS media part, from a URL a stranger may have chosen.
 *
 * ⛔ **THE SIGNATURE PROVES INFOBIP SENT THE BODY. IT PROVES NOTHING ABOUT WHERE
 * THE URL INSIDE IT POINTS.** {@see InfobipWebhookVerifier} is what makes the
 * webhook trustworthy as a *delivery*; the media address is content, and content
 * is untrusted for exactly as long as it is content. Every constraint below is
 * therefore non-negotiable and each is asserted by a test — the same posture
 * {@see ShortLinkResolver} takes over a pasted Google link,
 * and for the same reason.
 *
 *   - `https` only — a plaintext fetch of a customer's photograph is not one
 *     this platform makes, and `file:`, `gopher:` and friends die here
 *   - a **hostname allowlist**, checked **before a socket opens**
 *   - any host resolving to a loopback, private or link-local address rejected
 *   - **redirects are not followed at all** — see below
 *   - a short timeout, so a stalled CDN is a retry rather than a held worker
 *   - a **byte ceiling enforced on the stream**, not on a header we were handed
 *   - a content-type allowlist, checked against the response header **and**
 *     against the file's own first bytes
 *
 * ⛔ **REDIRECTS ARE NOT FOLLOWED, WHICH IS STRICTER THAN THE TWO SIBLINGS.**
 * `ShortLinkResolver` follows three hops because a redirect is the mechanism a
 * short link exists to provide, and `DirectFetchGateway` caps a chain at three
 * because a page may legitimately move. Neither is true here: a carrier serving
 * media serves it, and a 302 out of an allowlisted host is the exact shape of an
 * allowlist being walked around. A redirect is a refusal.
 *
 * ⚠️ **THE ALLOWLIST IS CONFIGURATION AND ITS DEFAULT IS EMPTY** (4168).
 * `config('services.infobip.media_hosts')`, plus the host of the account's own
 * `base_url` — which is a real, verified value rather than a guess, and needs no
 * configuring. ⛔ **The inbound media host itself is NOT verified against a raw
 * artefact** and could not be from this machine: `CLAUDE.md` forbids writing a
 * vendor string from memory, so what ships is a list an operator fills from a
 * real delivery. The failure direction is a refusal recorded as a row, never a
 * fetch from a host nobody checked.
 *
 * ⚠️ **IT IS ON `outboundHttpPermittedFiles()` AND ADDS NO HOST TO THE SCANNED
 * SET**, which is Infobip's case exactly (1576): the account's base URL is
 * per-account, there is no literal to read, and the subprocessor row for Infobip
 * already exists in §2. Nothing here reaches a vendor that is not already named.
 */
final class InboundMediaFetcher
{
    /**
     * The ceiling on one media part.
     *
     * ⚠️ **BYTES, AND CHOSEN ABOVE THE CARRIER'S OWN LIMIT RATHER THAN BELOW
     * IT.** US carriers cap MMS well under this; a ceiling *tighter* than what
     * a carrier will actually deliver would refuse real customer photographs
     * and report it as an attack. What this bounds is memory on a queue worker
     * fetching an address somebody else chose.
     */
    private const int MAX_BYTES = 3_000_000;

    /**
     * Short: a stalled CDN should become a retry, not a held worker.
     *
     * ⚠️ **IT BOUNDS THE REQUEST, AND THE BYTE CEILING BOUNDS MEMORY — NEITHER
     * OF THEM BOUNDS A SLOW *BODY*, WHICH IS STATED RATHER THAN CLAIMED.** With
     * `stream => true` the response returns once the headers are in, so a server
     * dribbling three megabytes one byte at a time is inside both limits. What
     * actually bounds it is the queue's own job timeout with
     * {@see CaptureInboundMediaJob::$failOnTimeout} true, which
     * kills the worker and lands the part on `failed()` as
     * {@see InboundMediaOutcome::RefusedUnreachable}. A wall-clock deadline in
     * this loop would be tighter, and it is deliberately not added here because
     * nothing in this harness can drive it red — an untestable guard on the
     * untrusted path is the protection-asserted-before-it-is-true shape
     * (314–316), and the backstop above is real and already configured.
     */
    public const int TIMEOUT_SECONDS = 15;

    public function timeoutSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('sms.inbound_media.timeout_seconds');
    }

    /** How much is read from the stream at a time. */
    private const int CHUNK_BYTES = 65_536;

    /**
     * The types this platform keeps, and the bytes each one must actually start
     * with.
     *
     * ⛔ **A DECLARED TYPE IS NOT A TYPE.** The header is written by whoever
     * served the file. A challenge page, an HTML error, a PDF or an executable
     * served as `image/jpeg` would otherwise be written to the object store and
     * later handed to a browser with that Content-Type on it. The prefixes are
     * the format's own published magic numbers, and a disagreement is refused
     * rather than resolved in favour of either side.
     *
     * ⚠️ **NO VIDEO AND NO AUDIO, DELIBERATELY.** Skill 12 is *photo* intake and
     * `29` §13's soft-launch OUT list has no vision or media analysis in it — so
     * the honest ceiling is the one thing the product does something with. A
     * video arrives, is recorded as `refused_content_type`, and the message
     * still reaches the owner.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED_TYPES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1A\n"],
        'image/gif' => ['GIF87a', 'GIF89a'],
        // RIFF....WEBP — the four size bytes between are checked separately.
        'image/webp' => ['RIFF'],
    ];

    public function fetch(string $url): InboundMediaFetch
    {
        if (! $this->addressIsPermitted($url)) {
            // Refused before a socket opens. The test for this asserts
            // Http::assertNothingSent(), which is the only way to prove it.
            return InboundMediaFetch::refused(InboundMediaOutcome::RefusedUntrustedHost);
        }

        try {
            $response = VendorLog::timed(
                'infobip_inbound_media',
                'GET',
                $url,
                fn () => Http::timeout($this->timeoutSeconds())
                    // See the class docblock: a redirect out of an allowlisted
                    // host is the allowlist being walked around, so the client
                    // is told not to follow one and a 3xx falls through to the
                    // status check below.
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->get($url),
            );
        } catch (ConnectionException) {
            VendorLog::failure('infobip_inbound_media', 'GET', $url, ConnectionException::class);

            return InboundMediaFetch::refused(InboundMediaOutcome::RefusedUnreachable);
        }

        if ($response->status() !== 200) {
            // ⚠️ **`!== 200` RATHER THAN `>= 400`.** A 204, a 206 and a 302 are
            // all "this is not the picture", and treating a redirect as success
            // would store the body of a redirect page.
            return InboundMediaFetch::refused(InboundMediaOutcome::RefusedUnreachable);
        }

        $declared = $this->declaredContentType($response->header('Content-Type'));

        if ($declared === null) {
            return InboundMediaFetch::refused(InboundMediaOutcome::RefusedContentType);
        }

        // The cheap refusal first, when the server was honest enough to say.
        $length = $response->header('Content-Length');

        if ($length !== '' && ctype_digit($length) && (int) $length > self::MAX_BYTES) {
            return InboundMediaFetch::refused(InboundMediaOutcome::RefusedTooLarge);
        }

        $bytes = $this->read($response->toPsrResponse()->getBody());

        if ($bytes === null) {
            // ⛔ **THE HEADER IS NOT THE CEILING.** A `Content-Length` that lies,
            // or is absent, is exactly how a size check that trusts it is
            // defeated — so the stream is read with a hard stop of its own and
            // this is the branch that fires when it trips.
            return InboundMediaFetch::refused(InboundMediaOutcome::RefusedTooLarge);
        }

        if (! $this->bytesMatch($declared, $bytes)) {
            return InboundMediaFetch::refused(InboundMediaOutcome::RefusedContentType);
        }

        return InboundMediaFetch::stored($bytes, $declared);
    }

    /**
     * Read at most `MAX_BYTES`, and answer null the moment there is more.
     *
     * ⚠️ **`MAX_BYTES + 1` IS READ ON PURPOSE.** Stopping at exactly the ceiling
     * cannot tell a file of precisely that size from one that was truncated, and
     * a truncated JPEG stored as a whole one is a corrupt picture nobody can
     * explain later.
     */
    private function read(StreamInterface $stream): ?string
    {
        $bytes = '';

        while (! $stream->eof()) {
            $bytes .= $stream->read(self::CHUNK_BYTES);

            if (strlen($bytes) > self::MAX_BYTES) {
                return null;
            }
        }

        return $bytes;
    }

    /**
     * The response's own type, stripped of its parameters, or null when it is
     * not one this platform keeps.
     */
    private function declaredContentType(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        // `image/jpeg; charset=binary` and `IMAGE/JPEG` are both ordinary.
        $type = mb_strtolower(trim(explode(';', $header, 2)[0]));

        return array_key_exists($type, self::ALLOWED_TYPES) ? $type : null;
    }

    /**
     * Whether the file's own first bytes agree with the type it claimed.
     */
    private function bytesMatch(string $type, string $bytes): bool
    {
        foreach (self::ALLOWED_TYPES[$type] as $magic) {
            if (! str_starts_with($bytes, $magic)) {
                continue;
            }

            // WebP is `RIFF`, four little-endian size bytes, then `WEBP`. The
            // prefix alone would also accept a WAV or an AVI, which are RIFF
            // containers too and are not pictures.
            if ($type === 'image/webp') {
                return strlen($bytes) >= 12 && substr($bytes, 8, 4) === 'WEBP';
            }

            return true;
        }

        return false;
    }

    /**
     * Scheme, allowlist and address, all before a socket opens.
     */
    private function addressIsPermitted(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (mb_strtolower($parts['scheme']) !== 'https') {
            return false;
        }

        $host = mb_strtolower($parts['host']);

        return in_array($host, $this->allowedHosts(), true) && $this->hostIsPublic($host);
    }

    /**
     * The configured hosts, plus the account's own API host.
     *
     * ⚠️ **THE BASE URL IS THE ONE ENTRY NOBODY HAS TO CONFIGURE**, and it is
     * not a guess: it is the host this application already talks to for every
     * send. `config/services.php` explains why there is no literal to write.
     *
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $configured = config('services.infobip.media_hosts');

        $hosts = is_array($configured)
            ? array_values(array_filter(array_map(
                fn (mixed $host): string => is_string($host) ? mb_strtolower(trim($host)) : '',
                $configured,
            )))
            : [];

        $baseUrl = config('services.infobip.base_url');

        if (is_string($baseUrl) && $baseUrl !== '') {
            // A bare host with no scheme is what an operator usually pastes, and
            // parse_url reads that as a path rather than a host.
            $base = str_contains($baseUrl, '//') ? $baseUrl : 'https://'.$baseUrl;
            $host = parse_url($base, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = mb_strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Reject any host that resolves to a loopback, private or link-local
     * address.
     *
     * ⚠️ **THE ALLOWLIST ALREADY MAKES THIS CLOSE TO UNREACHABLE, AND THAT IS
     * EXACTLY THE ASSUMPTION AN SSRF CHECK MUST NOT MAKE** —
     * `ShortLinkResolver`'s own words. Allowlists get widened, and this is the
     * layer that still holds when one is. It is also the layer that survives a
     * DNS answer changing under us between deployments.
     */
    private function hostIsPublic(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->addressIsPublic($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($address) || ! $this->addressIsPublic($address)) {
                return false;
            }
        }

        return true;
    }

    private function addressIsPublic(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
