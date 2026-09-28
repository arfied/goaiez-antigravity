<?php

declare(strict_types=1);

namespace App\Modules\X137\Actions;

use App\Modules\X137\Events\CallAttributed;
use App\Modules\X137\Events\VisitJoinedToCall;
use App\Modules\X137\Models\CallToken;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class CallAttributeAction
{
    public const TTL_MINUTES = 30;

    public function __construct(
        private readonly DefaultsRegistry $registry
    ) {}

    private function ttlMinutes(): int
    {
        return $this->registry->int('attribution.call.ttl_minutes');
    }

    /**
     * Allocate a DNI call token from the business pool.
     */
    public function allocateFromPool(
        int $businessId,
        string $visitorSessionToken,
        string $campaignSource = 'google_cpc',
        ?int $ttlMinutes = null
    ): CallToken {
        $ttlMinutes ??= $this->ttlMinutes();
        if (trim((string) $visitorSessionToken) === '') {
            throw new \DomainException('VISITOR_SESSION_TOKEN_REQUIRED');
        }

        $poolNumbers = DB::table('dni_pool_numbers')
            ->where('business_id', $businessId)
            ->pluck('phone_number')
            ->filter(fn ($n) => trim((string) $n) !== '')
            ->values()
            ->toArray();

        $activeTokens = CallToken::where('business_id', $businessId)
            ->where('status', 'active')
            ->where('expires_at', '>', Carbon::now())
            ->whereIn('allocated_number', $poolNumbers)
            ->pluck('allocated_number')
            ->toArray();

        $availableNumbers = array_diff($poolNumbers, $activeTokens);

        if (empty($availableNumbers)) {
            $setting = DB::table('dni_pool_settings')->where('business_id', $businessId)->first();

            if ($setting === null || trim((string) $setting->fallback_number) === '') {
                throw new \DomainException('BUSINESS_NOT_CONFIGURED_FOR_DNI');
            }

            $fallback = $setting->fallback_number;

            return CallToken::create([
                'business_id' => $businessId,
                'visitor_session_token' => $visitorSessionToken,
                'allocated_number' => $fallback,
                'campaign_source' => $campaignSource,
                'whisper_text' => "Call from {$campaignSource}",
                'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
                'status' => 'unattributed',
                'is_static' => false,
            ]);
        }

        $allocatedNumber = array_values($availableNumbers)[0];

        return $this->allocateToken($businessId, $visitorSessionToken, $allocatedNumber, $campaignSource, $ttlMinutes, false);
    }

    /**
     * Allocate a DNI call token for a visitor (G3-11, G8-13, G13-19).
     */
    public function allocateToken(
        int $businessId,
        string $visitorSessionToken,
        string $allocatedNumber,
        string $campaignSource = 'google_cpc',
        ?int $ttlMinutes = null,
        bool $offlineCampaign = false
    ): CallToken {
        $ttlMinutes ??= $this->ttlMinutes();
        if ($offlineCampaign) {
            $conflicting = CallToken::where('business_id', $businessId)
                ->where('allocated_number', $allocatedNumber)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', Carbon::now());
                })
                ->where('campaign_source', '!=', $campaignSource)
                ->first();

            if ($conflicting !== null) {
                throw new \DomainException('NUMBER_ALREADY_ASSIGNED_TO_DIFFERENT_CAMPAIGN');
            }

            $existing = CallToken::where('business_id', $businessId)
                ->where('campaign_source', $campaignSource)
                ->where('status', 'active')
                ->where('expires_at', '>', Carbon::now())
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $allocatedNumber = $existing->allocated_number;
            }
        }

        return CallToken::create([
            'business_id' => $businessId,
            'visitor_session_token' => $visitorSessionToken,
            'allocated_number' => $allocatedNumber,
            'campaign_source' => $campaignSource,
            'whisper_text' => "Call from {$campaignSource}",
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
            'status' => 'active',
            'is_static' => false,
        ]);
    }

    /**
     * Allocate a static number for an offline campaign (G13-24).
     */
    public function allocateStaticToken(
        int $businessId,
        string $allocatedNumber,
        string $campaignSource
    ): CallToken {
        $conflicting = CallToken::where('business_id', $businessId)
            ->where('allocated_number', $allocatedNumber)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->where('campaign_source', '!=', $campaignSource)
            ->first();

        if ($conflicting !== null) {
            throw new \DomainException('NUMBER_ALREADY_ASSIGNED_TO_DIFFERENT_CAMPAIGN');
        }

        return CallToken::create([
            'business_id' => $businessId,
            'visitor_session_token' => null,
            'allocated_number' => $allocatedNumber,
            'campaign_source' => $campaignSource,
            'whisper_text' => "Call from {$campaignSource}",
            'expires_at' => null,
            'status' => 'active',
            'is_static' => true,
        ]);
    }

    /**
     * Attribute inbound call to visitor visit session within TTL (TEST ANCHOR).
     */
    public function attributeCall(int $businessId, int $callId, string $dialedNumber): array
    {
        $token = CallToken::where('business_id', $businessId)
            ->where('allocated_number', $dialedNumber)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        // 1. If no token or token is expired -> unattributed (TEST ANCHOR)
        if ($token === null || (! $token->is_static && $token->expires_at->isPast())) {
            if ($token !== null && ! $token->is_static) {
                $token->update(['status' => 'expired_unattributed']);
            }

            return [
                'status' => 'unattributed',
                'call_id' => $callId,
                'dialed_number' => $dialedNumber,
                'message' => 'No active call token found within TTL window',
            ];
        }

        // 2. Call within TTL joins the visit, or static number attributes to campaign
        if (! $token->is_static) {
            $token->update([
                'status' => 'joined',
                'joined_call_id' => $callId,
            ]);
        }

        Event::dispatch(new CallAttributed(
            businessId: $businessId,
            callId: $callId,
            campaignSource: $token->campaign_source,
            whisperText: $token->whisper_text
        ));

        if (! $token->is_static) {
            Event::dispatch(new VisitJoinedToCall(
                businessId: $businessId,
                callId: $callId,
                visitorSessionToken: $token->visitor_session_token
            ));
        }

        return [
            'status' => 'attributed',
            'call_id' => $callId,
            'visitor_session_token' => $token->visitor_session_token,
            'campaign_source' => $token->campaign_source,
            'whisper_text' => $token->whisper_text,
        ];
    }
}
