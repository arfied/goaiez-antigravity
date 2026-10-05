<?php

declare(strict_types=1);

namespace App\Services\Voice\Live;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * The call token call start hands the voice worker, and every later voice brain request carries back (AI receptionist plan,
 * 2026-10-05).
 *
 * The token is the claim — business, call, time issued — encrypted under the application key, so it cannot be read or
 * forged by anyone who does not hold that key: `UnsubscribeLinks`' shape. Every later endpoint takes the tenant from
 * {@see self::open()} and never from a request field (`tests/Feature/Architecture/VoiceTest.php`).
 *
 * ⚠️ Not `CallToken`: that name is X-137's, a different thing on a different table.
 */
final class LiveCallTokens
{
    /**
     * How long a token opens for: longer than any call the receptionist will hold. `voice.max_call_seconds` (a later wave)
     * ends calls well inside it.
     */
    public const int LIFETIME_SECONDS = 4 * 3600;

    /** Refused before the cipher, so an oversized string is no work on a signed endpoint. */
    private const int MAX_TOKEN_LENGTH = 1024;

    public function mint(int $businessId, int $callId): string
    {
        $payload = json_encode([
            'b' => $businessId,
            'c' => $callId,
            't' => CarbonImmutable::now()->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        // URL-safe, so the token can sit in a path segment (`/calls/{token}/…`).
        return strtr(Crypt::encryptString($payload), '+/=', '-_~');
    }

    /**
     * The claim a token carries, or null for anything forged, edited, truncated, expired or stamped in the future — one
     * answer for every failure, `UnsubscribeLinks::open()`'s rule.
     */
    public function open(string $token): ?LiveCallClaim
    {
        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        try {
            $plain = Crypt::decryptString(strtr($token, '-_~', '+/='));
        } catch (DecryptException) {
            return null;
        }

        try {
            /** @var mixed $payload */
            $payload = json_decode($plain, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $businessId = $payload['b'] ?? null;
        $callId = $payload['c'] ?? null;
        $issuedAt = $payload['t'] ?? null;

        if (! is_int($businessId) || ! is_int($callId) || ! is_int($issuedAt)) {
            return null;
        }

        $issued = CarbonImmutable::createFromTimestamp($issuedAt);

        if ($issued->isFuture() || $issued->addSeconds(self::LIFETIME_SECONDS)->isPast()) {
            return null;
        }

        return new LiveCallClaim($businessId, $callId, $issued);
    }
}
