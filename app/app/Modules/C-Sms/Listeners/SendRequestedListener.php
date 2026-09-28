<?php

declare(strict_types=1);

namespace App\Modules\CSms\Listeners;

use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Models\Customer;
use App\Modules\CSms\Actions\SmsSendAction;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\CSms\Events\SendSettled;
use App\Modules\X204\Actions\ConsentDecideAction;
use App\Services\Consent\ConsentService;
use Illuminate\Support\Facades\Event;

final class SendRequestedListener
{
    public function __construct(
        private readonly ConsentDecideAction $decideAction,
        private readonly SmsSendAction $sendAction
    ) {}

    public function handle(SendRequested $event): void
    {
        $consentState = match (OutreachPurpose::tryFrom($event->messageClass) ?? OutreachPurpose::Transactional) {
            OutreachPurpose::Marketing => 'opted_in',
            OutreachPurpose::Transactional => 'transactional',
        };

        $decision = $this->decideAction->handle(
            $event->businessId,
            $event->recipientPhone,
            'sms',
            $consentState
        );

        if (! $decision['granted']) {
            $customer = Customer::where('business_id', $event->businessId)->where('phone', $event->recipientPhone)->first();
            if ($customer) {
                $purposeEnum = OutreachPurpose::tryFrom($event->messageClass) ?? OutreachPurpose::Transactional;
                $legacyDecision = app(ConsentService::class)->decide($customer, OutreachChannel::Sms, $purposeEnum);
                if ($legacyDecision->isGranted()) {
                    throw new \Exception('UNRESOLVED C-Sms design "two consent engines disagree: X-204 refused, legacy granted"');
                }
            }

            Event::dispatch(new SendSettled($event->businessId, $event->compositionId, $event->source, 'refused', (string) ($decision['reason'] ?? 'CONSENT_NOT_GRANTED')));

            return;
        }

        $result = $this->sendAction->handle(
            $event->businessId,
            $event->recipientPhone,
            $event->body,
            $event->messageClass,
            '12:00',
            $decision['permit_id'] ?? 0,
            'csms:'.$event->compositionId
        );

        Event::dispatch(new SendSettled(
            $event->businessId,
            $event->compositionId,
            $event->source,
            ($result['status'] ?? '') === 'accepted' ? 'sent' : 'refused',
            ($result['status'] ?? '') === 'accepted' ? null : (string) ($result['reason'] ?? $result['status'] ?? 'UNKNOWN'),
        ));
    }
}
