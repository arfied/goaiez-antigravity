<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The AI receptionist's voice worker signs every request to the voice brain API (plan 2026-10-05, PLAN-AI-RECEPTIONIST).
 *
 * The worker runs outside this host and carries live call audio; this application stays the brain. Every request carries
 * `X-Voice-Timestamp` (unix seconds) and `X-Voice-Signature` = hex HMAC-SHA256 of `"{timestamp}.{raw body}"` with the
 * `voice_worker_secret` credential — the shape the Infobip webhook verifier already uses. A request is refused when the key is
 * not set (never "open when unconfigured"), when it is older or newer than `voice.worker.max_request_age_seconds`, when the
 * signature does not match, or when the same signature was already accepted (a replay inside the window).
 *
 * ⛔ The tenant is never taken from what the worker sends: later endpoints derive it from a signed call token only.
 */
final class VerifyVoiceWorker
{
    public const string CREDENTIAL = 'voice_worker_secret';

    public const string TIMESTAMP_HEADER = 'X-Voice-Timestamp';

    public const string SIGNATURE_HEADER = 'X-Voice-Signature';

    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformCredentials::has(self::CREDENTIAL)) {
            return $this->refuse();
        }

        $timestamp = (string) $request->header(self::TIMESTAMP_HEADER, '');
        $signature = strtolower(trim((string) $request->header(self::SIGNATURE_HEADER, '')));
        if (! ctype_digit($timestamp) || $signature === '') {
            return $this->refuse();
        }

        $maxAge = app(DefaultsRegistry::class)->int('voice.worker.max_request_age_seconds');
        if (abs(time() - (int) $timestamp) > $maxAge) {
            return $this->refuse();
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), PlatformCredentials::get(self::CREDENTIAL));
        if (! hash_equals($expected, $signature)) {
            return $this->refuse();
        }

        // A signed request is accepted once; the same signature again inside the window is a replay.
        if (! Cache::add('voice-worker:signature:'.$signature, true, $maxAge * 2)) {
            return $this->refuse();
        }

        return $next($request);
    }

    private function refuse(): Response
    {
        return response()->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
    }
}
