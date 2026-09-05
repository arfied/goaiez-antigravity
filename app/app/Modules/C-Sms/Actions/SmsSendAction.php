<?php

declare(strict_types=1);

namespace App\Modules\CSms\Actions;

use App\Contracts\MessageSender;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\ReviewInviteKind;
use App\Models\Customer;
use App\Modules\CSms\Domain\SmsComposer;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use Illuminate\Support\Facades\DB;

final class SmsSendAction
{
    public function __construct(
        private readonly MessageSender $sender,
        private readonly SmsComposer $composer
    ) {}

    public function handle(
        int $businessId,
        string $recipientPhone,
        string $body,
        string $messageClass = 'transactional',
        string $recipientLocalTime = '12:00',
        int $permitId = 0
    ): array {
        if ($permitId === 0) {
            return $this->composer->send($businessId, $recipientPhone, $body, $messageClass, $recipientLocalTime);
        }

        $customer = Customer::where('business_id', $businessId)->where('phone', $recipientPhone)->first();

        if (! $customer) {
            return [
                'status' => 'refused',
                'reason' => 'CUSTOMER_UNKNOWN',
            ];
        }

        $purposeEnum = match ($messageClass) {
            'marketing' => OutreachPurpose::Marketing,
            default => OutreachPurpose::Transactional,
        };

        $decision = app(ConsentService::class)->decide($customer, OutreachChannel::Sms, $purposeEnum);

        if (! $decision->isGranted()) {
            if ($permitId > 0) {
                $reasonName = $decision->reason->name ?? 'Unknown';
                throw new \Exception('UNRESOLVED C-Sms design "two consent engines disagree: X-204 granted, legacy refused ('.$reasonName.')"');
            }

            return [
                'status' => 'refused',
                'reason' => $decision->reason->value ?? $decision->reason->name,
            ];
        }

        $permit = $decision->permit;
        $key = SendKey::for($permit, 'csms:'.uniqid());

        $message = OutboundMessage::for(
            permit: $permit,
            body: $body,
            key: $key,
            purpose: $purposeEnum,
        );

        $outcome = $this->sender->send($message);

        if ($outcome->status->value === 'accepted' && $outcome->providerMessageId) {
            $expectedPurpose = $messageClass === 'marketing' ? ReviewInviteKind::Invite->outreachPurpose() : 'transactional';
            DB::table('outreach_messages')
                ->where('provider_msg_id', $outcome->providerMessageId)
                ->update(['purpose' => $expectedPurpose]);
        }

        return [
            'status' => $outcome->status->value,
            'provider_message_id' => $outcome->providerMessageId,
        ];
    }
}
