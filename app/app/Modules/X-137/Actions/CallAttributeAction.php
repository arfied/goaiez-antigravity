<?php

declare(strict_types=1);

namespace App\Modules\X137\Actions;

use App\Modules\X137\Events\CallAttributed;
use App\Modules\X137\Events\VisitJoinedToCall;
use App\Modules\X137\Models\CallToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class CallAttributeAction
{
    /**
     * Allocate a DNI call token for a visitor (G3-11, G8-13, G13-19).
     */
    public function allocateToken(
        int $businessId,
        string $visitorSessionToken,
        string $allocatedNumber,
        string $campaignSource = 'google_cpc',
        int $ttlMinutes = 30,
        bool $offlineCampaign = false
    ): CallToken {
        if ($offlineCampaign) {
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
        if ($token === null || $token->expires_at->isPast()) {
            if ($token !== null) {
                $token->update(['status' => 'expired_unattributed']);
            }

            return [
                'status' => 'unattributed',
                'call_id' => $callId,
                'dialed_number' => $dialedNumber,
                'message' => 'No active call token found within TTL window',
            ];
        }

        // 2. Call within TTL joins the visit (TEST ANCHOR)
        $token->update([
            'status' => 'joined',
            'joined_call_id' => $callId,
        ]);

        Event::dispatch(new CallAttributed(
            businessId: $businessId,
            callId: $callId,
            campaignSource: $token->campaign_source,
            whisperText: $token->whisper_text
        ));

        Event::dispatch(new VisitJoinedToCall(
            businessId: $businessId,
            callId: $callId,
            visitorSessionToken: $token->visitor_session_token
        ));

        return [
            'status' => 'attributed',
            'call_id' => $callId,
            'visitor_session_token' => $token->visitor_session_token,
            'campaign_source' => $token->campaign_source,
            'whisper_text' => $token->whisper_text,
        ];
    }
}
