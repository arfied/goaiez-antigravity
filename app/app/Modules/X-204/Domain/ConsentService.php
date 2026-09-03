<?php

declare(strict_types=1);

namespace App\Modules\X204\Domain;

use App\Modules\X204\Events\AttestationRecorded;
use App\Modules\X204\Events\ConsentDecided;
use App\Modules\X204\Events\PermitGranted;
use App\Modules\X204\Events\SuppressionAdded;
use App\Modules\X204\Models\ImportAttestation;
use App\Modules\X204\Models\SendPermit;
use App\Modules\X204\Models\Suppression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class ConsentService
{
        public const P060_CODES = [
        'archived', 'deleted', 'merged_away', 'no_identifier', 'unparseable_identifier',
        'caller_mismatch', 'opted_out', 'do_not_call', 'litigator', 'number_reassigned',
        'no_consent_record', 'registry_not_loaded', 'suppression_unreadable', 'state_unknown',
        'quiet_hours', 'consent_too_weak_for_state', 'tenant_paused', 'global_halt',
        'insufficient_credit', 'channel_unavailable', 'message_too_long',
    ];

    private const VALID_CONSENT_STATES = [
        'opted_in',
        'transactional',
        'attested',
        'customer_initiated',
    ];

    /**
     * Decide if a message may be sent. Unknown state always returns refusal, never a grant.
     */
    public function decide(int $businessId, string $recipientPhone, string $channel = 'sms', string $state = 'opted_in'): array
    {
        return DB::transaction(function () use ($businessId, $recipientPhone, $channel, $state) {
            // 1. Suppression list check
            $suppressed = Suppression::where('business_id', $businessId)
                ->where('recipient_phone', $recipientPhone)
                ->where('channel', $channel)
                ->first();

            if ($suppressed !== null) {
                $permit = SendPermit::create([
                    'business_id' => $businessId,
                    'recipient_phone' => $recipientPhone,
                    'channel' => $channel,
                    'permit_status' => 'refused',
                    'refusal_reason' => 'SUPPRESSED: '.$suppressed->reason,
                ]);

                Event::dispatch(new ConsentDecided(
                    businessId: $businessId,
                    recipientPhone: $recipientPhone,
                    granted: false,
                    reason: 'SUPPRESSED'
                ));

                return [
                    'granted' => false,
                    'permit_id' => $permit->id,
                    'reason' => 'SUPPRESSED',
                ];
            }

            // 2. State validity check (TEST ANCHOR: unknown state returns refusal, never a grant)
            if (! in_array($state, self::VALID_CONSENT_STATES, true)) {
                $permit = SendPermit::create([
                    'business_id' => $businessId,
                    'recipient_phone' => $recipientPhone,
                    'channel' => $channel,
                    'permit_status' => 'refused',
                    'refusal_reason' => 'UNKNOWN_STATE: '.$state,
                ]);

                Event::dispatch(new ConsentDecided(
                    businessId: $businessId,
                    recipientPhone: $recipientPhone,
                    granted: false,
                    reason: 'UNKNOWN_STATE'
                ));

                return [
                    'granted' => false,
                    'permit_id' => $permit->id,
                    'reason' => 'UNKNOWN_STATE',
                ];
            }

            // 3. Grant permit
            $permit = SendPermit::create([
                'business_id' => $businessId,
                'recipient_phone' => $recipientPhone,
                'channel' => $channel,
                'permit_status' => 'granted',
                'expires_at' => now()->addMinutes(15),
            ]);

            Event::dispatch(new PermitGranted(
                businessId: $businessId,
                permitId: $permit->id,
                recipientPhone: $recipientPhone,
                channel: $channel
            ));

            Event::dispatch(new ConsentDecided(
                businessId: $businessId,
                recipientPhone: $recipientPhone,
                granted: true
            ));

            return [
                'granted' => true,
                'permit_id' => $permit->id,
                'expires_at' => $permit->expires_at?->toIso8601String(),
            ];
        });
    }

    public function suppress(int $businessId, string $recipientPhone, string $channel = 'sms', string $reason = 'opt_out'): Suppression
    {
        $suppression = Suppression::updateOrCreate(
            ['business_id' => $businessId, 'recipient_phone' => $recipientPhone, 'channel' => $channel],
            ['reason' => $reason, 'suppressed_at' => now()]
        );

        Event::dispatch(new SuppressionAdded(
            businessId: $businessId,
            recipientPhone: $recipientPhone,
            reason: $reason
        ));

        return $suppression;
    }

    public function lift(int $businessId, string $recipientPhone, string $channel = 'sms'): bool
    {
        return (bool) Suppression::where('business_id', $businessId)
            ->where('recipient_phone', $recipientPhone)
            ->where('channel', $channel)
            ->delete();
    }

    public function recordAttestation(int $businessId, string $hash, string $source, int $count, string $attestedBy): ImportAttestation
    {
        $attestation = ImportAttestation::create([
            'business_id' => $businessId,
            'attestation_hash' => $hash,
            'source' => $source,
            'contacts_count' => $count,
            'attested_by' => $attestedBy,
            'attested_at' => now(),
        ]);

        Event::dispatch(new AttestationRecorded(
            businessId: $businessId,
            attestationId: $attestation->id,
            attestationHash: $hash,
            contactsCount: $count
        ));

        return $attestation;
    }

    public function getSendCountInWindow(int $businessId, string $recipientPhone, string $channel, int $hours): int
    {
        return SendPermit::where('business_id', $businessId)
            ->where('recipient_phone', $recipientPhone)
            ->where('channel', $channel)
            ->where('permit_status', 'granted')
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
    }
}
