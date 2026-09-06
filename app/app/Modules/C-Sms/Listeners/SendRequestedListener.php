<?php

declare(strict_types=1);

namespace App\Modules\CSms\Listeners;

use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Models\Customer;
use App\Modules\CSms\Actions\SmsSendAction;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X204\Actions\ConsentDecideAction;
use App\Services\Consent\ConsentService;

final class SendRequestedListener
{
    public function __construct(
        private readonly ConsentDecideAction $decideAction,
        private readonly SmsSendAction $sendAction
    ) {}

    public function handle(SendRequested $event): void
    {
        $consentState = match ($event->messageClass) {
            'marketing' => 'opted_in',
            default => 'transactional',
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
                $purposeEnum = match ($event->messageClass) {
                    'marketing' => OutreachPurpose::Marketing,
                    default => OutreachPurpose::Transactional,
                };
                $legacyDecision = app(ConsentService::class)->decide($customer, OutreachChannel::Sms, $purposeEnum);
                if ($legacyDecision->isGranted()) {
                    throw new \Exception('UNRESOLVED C-Sms design "two consent engines disagree: X-204 refused, legacy granted"');
                }
            }

            return;
        }

        $this->sendAction->handle(
            $event->businessId,
            $event->recipientPhone,
            $event->body,
            $event->messageClass,
            '12:00',
            $decision['permit_id'] ?? 0,
            'csms:'.$event->compositionId
        );
    }
}
