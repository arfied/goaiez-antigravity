<?php

declare(strict_types=1);

namespace App\Modules\CSms\Actions;

use App\Contracts\MessageSender;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\ReviewInviteKind;
use App\Enums\SendRefusalReason;
use App\Models\Customer;
use App\Modules\CSms\Domain\SmsComposer;
use App\Modules\CSms\Exceptions\ConsentDisagreementException;
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
        int $permitId = 0,
        string $occasion = ''
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

        $purposeEnum = OutreachPurpose::tryFrom($messageClass) ?? OutreachPurpose::Transactional;

        $decision = app(ConsentService::class)->decide($customer, OutreachChannel::Sms, $purposeEnum);

        if (! $decision->isGranted()) {
            if ($permitId > 0) {
                $isInfrastructure = match ($decision->reason) {
                    SendRefusalReason::RegistryNotLoaded,
                    SendRefusalReason::SuppressionUnreadable,
                    SendRefusalReason::TenantPaused,
                    SendRefusalReason::GlobalHalt,
                    SendRefusalReason::InsufficientCredit,
                    SendRefusalReason::ChannelUnavailable => true,
                    SendRefusalReason::Archived,
                    SendRefusalReason::Deleted,
                    SendRefusalReason::MergedAway,
                    SendRefusalReason::NoIdentifier,
                    SendRefusalReason::UnparseableIdentifier,
                    SendRefusalReason::CallerMismatch,
                    SendRefusalReason::OptedOut,
                    SendRefusalReason::DoNotCall,
                    SendRefusalReason::Litigator,
                    SendRefusalReason::NumberReassigned,
                    SendRefusalReason::NoConsentRecord,
                    SendRefusalReason::RepliesOnly,
                    SendRefusalReason::StateUnknown,
                    SendRefusalReason::QuietHours,
                    SendRefusalReason::ConsentTooWeakForState,
                    SendRefusalReason::MessageTooLong => false,
                };

                if (! $isInfrastructure) {
                    $reasonName = $decision->reason->name ?? 'Unknown';
                    throw new ConsentDisagreementException('Two consent engines disagree: X-204 granted, legacy refused ('.$reasonName.')');
                }
            }

            DB::table('outreach_messages')->insert([
                'business_id' => $businessId,
                'recipient_phone' => $recipientPhone,
                'channel' => 'sms',
                'status' => 'refused',
                'refusal_reason' => $decision->reason->value ?? $decision->reason->name,
                'purpose' => $purposeEnum->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'status' => 'refused',
                'reason' => $decision->reason->value ?? $decision->reason->name,
            ];
        }

        $permit = $decision->permit;
        if ($occasion === '') {
            throw new \InvalidArgumentException('SmsSendAction requires a deterministic occasion');
        }
        $key = SendKey::for($permit, $occasion);

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
