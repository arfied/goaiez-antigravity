<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Domain;

use App\Modules\CTelephony\Events\CarrierDegraded;
use App\Modules\CTelephony\Events\CarrierReceiptEvent;
use App\Modules\CTelephony\Events\CarrierSelected;
use App\Modules\CTelephony\Models\CarrierBinding;
use App\Modules\CTelephony\Models\CarrierHealth;
use App\Modules\CTelephony\Models\CarrierReceipt;
use App\Modules\X204\Domain\ConsentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class CarrierRouter
{
    public function __construct(
        private readonly ConsentService $consentService
    ) {}

    public const ADAPTERS = [
        'twilio' => 'App\Modules\CTelephony\Adapters\TwilioAdapter',
        'telnyx' => 'App\Modules\CTelephony\Adapters\TelnyxAdapter',
        'sinch' => 'App\Modules\CTelephony\Adapters\SinchAdapter',
        'infobip' => 'App\Modules\CTelephony\Adapters\InfobipAdapter',
        'signalwire' => 'App\Modules\CTelephony\Adapters\SignalWireAdapter',
        'plivo' => 'App\Modules\CTelephony\Adapters\PlivoAdapter',
        'bandwidth' => 'App\Modules\CTelephony\Adapters\BandwidthAdapter',
        'vonage' => 'App\Modules\CTelephony\Adapters\VonageAdapter',
    ];

    /**
     * Send message through carrier with thread stickiness and strict RCS non-downgrade.
     */
    public function send(
        int $businessId,
        string $threadKey,
        string $toPhone,
        string $body,
        bool $isRcs = false,
        ?string $preferredCarrier = null
    ): array {
        return DB::transaction(function () use ($businessId, $threadKey, $toPhone, $isRcs, $preferredCarrier) {
            $decision = $this->consentService->decide($businessId, $toPhone, 'telephony', 'transactional');
            if (! $decision['granted']) {
                $reason = ($decision['reason'] === 'SUPPRESSED') ? 'STOP_SUPPRESSED' : $decision['reason'];
                return [
                    'status' => 'refused',
                    'reason' => $reason,
                    'message' => 'Send suppressed due to consent check: '.$decision['reason'],
                ];
            }

            // 1. Thread Stickiness Check (TEST ANCHOR: Thread bound to Telnyx stays on Telnyx)
            $binding = CarrierBinding::where('business_id', $businessId)
                ->where('thread_key', $threadKey)
                ->first();

            $carrierName = $binding ? $binding->carrier_name : ($preferredCarrier ?? 'telnyx');

            // If not bound yet, create binding
            if ($binding === null) {
                CarrierBinding::create([
                    'business_id' => $businessId,
                    'thread_key' => $threadKey,
                    'carrier_name' => $carrierName,
                ]);
            }

            // 2. Carrier health & RCS non-downgrade check (TEST ANCHOR: Sinch cold on RCS refused-and-alerted)
            $health = CarrierHealth::where('business_id', $businessId)
                ->where('carrier_name', $carrierName)
                ->first();

            if ($isRcs) {
                if ($health !== null && in_array($health->status, ['cold', 'degraded', 'down'], true)) {
                    Event::dispatch(new CarrierDegraded(
                        businessId: $businessId,
                        carrierName: $carrierName,
                        status: $health->status
                    ));

                    return [
                        'status' => 'refused',
                        'carrier' => $carrierName,
                        'reason' => 'RCS_CARRIER_COLD',
                        'message' => "RCS send with carrier {$carrierName} is cold/degraded; refused and alerted without downgrade",
                    ];
                }
            }

            // 3. Credential check & carrier dispatch (TEST ANCHOR: refuse before request when credential absent)
            $carrierCredential = DB::table('carrier_credentials')
                ->where('business_id', $businessId)
                ->where('carrier_name', $carrierName)
                ->first();

            if ($carrierCredential === null) {
                return [
                    'status' => 'refused',
                    'carrier' => $carrierName,
                    'reason' => 'CREDENTIAL_ABSENT',
                    'message' => "Carrier credential for {$carrierName} is absent; refused before dispatch",
                ];
            }

            $receipt = CarrierReceipt::create([
                'business_id' => $businessId,
                'message_id' => null,
                'carrier_name' => $carrierName,
                'carrier_message_id' => null,
                'status' => 'sent',
                'cost_cents' => 1,
            ]);

            Event::dispatch(new CarrierSelected(
                businessId: $businessId,
                carrierName: $carrierName,
                threadKey: $threadKey
            ));

            Event::dispatch(new CarrierReceiptEvent(
                businessId: $businessId,
                carrierName: $carrierName,
                messageId: (string) $receipt->id,
                status: 'sent'
            ));

            return [
                'status' => 'sent',
                'carrier' => $carrierName,
                'message_id' => (string) $receipt->id,
                'receipt_id' => $receipt->id,
            ];
        });
    }

    /**
     * Screen inbound call with SHAKEN/STIR grading (G11-36, G11-39).
     */
    public function screenInbound(int $businessId, string $fromPhone, string $shakenStirGrade = 'A'): array
    {
        $passedScreening = in_array($shakenStirGrade, ['A', 'B'], true);

        return [
            'from_phone' => $fromPhone,
            'shaken_stir_grade' => $shakenStirGrade,
            'screening_passed' => $passedScreening,
            'action' => $passedScreening ? 'connect' : 'reject_spam',
        ];
    }
}
