<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The limiter guarding the chat start and turn doors (X-102).
 *
 * These routes write database rows (ChatSession and ChatTurn) on unauthenticated
 * paths, making the database a direct target. The limit must be tight enough
 * to prevent a denial of wallet or row exhaustion, but generous enough to
 * allow normal usage and accidental reloads.
 *
 * KEYED ON (VISITOR, BUSINESS), using HashedIp instead of the raw IP to
 * protect PII, following the convention set out in PublicAuditRateLimits.
 *
 * START LIMIT IS 5 PER MINUTE. A real user starts exactly one chat. Five allows
 * for a few immediate refreshes or network drops without locking out the
 * visitor, while capping the damage from a tight loop to five rows per
 * minute per attacking address.
 */
final class ChatRateLimits
{
    public const int START_PER_MINUTE = 5;

    /**
     * TURN LIMIT IS 60 PER MINUTE. A real user types and sends messages at human speed,
     * perhaps a burst of a few short messages followed by waiting. 60 allows for 1 message
     * per second on average, accommodating rapid bursts while capping abuse to 60 rows
     * per minute per attacking address.
     */
    public const int TURN_PER_MINUTE = 60;

    /**
     * CAPTURE LIMIT IS 5 PER MINUTE. A real user typically submits their contact details
     * once per chat session. Five allows for immediate resubmissions (e.g., correcting
     * a typo) or network retries, while capping the damage from a tight loop to five
     * pairs of chat_leads and Person rows per minute per attacking address.
     */
    public const int CAPTURE_PER_MINUTE = 5;

    public static function register(): void
    {
        RateLimiter::for('chat-start', fn (Request $request): Limit => Limit::perMinute(self::START_PER_MINUTE)
            ->by(self::chatKey($request))
            ->response(fn (): Response => response()->json(
                ['message' => 'Too many requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            )));

        RateLimiter::for('chat-turn', fn (Request $request): Limit => Limit::perMinute(self::TURN_PER_MINUTE)
            ->by(self::chatKey($request))
            ->response(fn (): Response => response()->json(
                ['message' => 'Too many requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            )));

        RateLimiter::for('chat-capture', fn (Request $request): Limit => Limit::perMinute(self::CAPTURE_PER_MINUTE)
            ->by(self::chatKey($request))
            ->response(fn (): Response => response()->json(
                ['message' => 'Too many requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            )));
    }

    private static function chatKey(Request $request): string
    {
        $key = $request->route('key');

        return (HashedIp::of($request) ?? 'chat:unknown-origin')
            .':'.(is_string($key) ? $key : 'unknown-business');
    }
}
