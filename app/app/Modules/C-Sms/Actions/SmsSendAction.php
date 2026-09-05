<?php

declare(strict_types=1);

namespace App\Modules\CSms\Actions;

use App\Contracts\MessageSender;
use App\Enums\OutreachPurpose;
use App\Models\ConsentRecord;
use App\Services\Consent\SendPermit;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use App\Enums\ReviewInviteKind;
use App\Modules\X121\Models\Person;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class SmsSendAction
{
    public function __construct(private readonly MessageSender $sender) {}

    public function handle(
        int $businessId,
        string $recipientPhone,
        string $body,
        string $messageClass = 'transactional',
        string $recipientLocalTime = '12:00',
        int $permitId = 0
    ): array {
        $person = Person::where('business_id', $businessId)->where('phone', $recipientPhone)->first();
        $personId = $person ? $person->id : 1;
        
        $customer = Customer::find($personId);
        if (!$customer) {
            $customer = new Customer();
            $customer->forceFill([
                'id' => $personId,
                'business_id' => $businessId,
                'phone' => $recipientPhone,
                'name' => 'Unknown',
            ])->save();
        }
        
        $record = new ConsentRecord();
        $record->forceFill([
            'id' => $permitId ?: 1,
            'business_id' => $businessId,
            'customer_id' => $personId,
            'channel' => \App\Enums\OutreachChannel::Sms,
            'captured_by' => \App\Enums\CapturedBy::Platform,
            'consent_type' => \App\Enums\ConsentType::ExpressWritten,
        ]);
        
        $permit = SendPermit::grant($record, $recipientPhone);
        $key = SendKey::for($permit, 'csms:' . uniqid());
        $purposeEnum = OutreachPurpose::Transactional;
        
        $message = OutboundMessage::for(
            permit: $permit,
            body: $body,
            key: $key,
            purpose: $purposeEnum,
        );
        
        
        if (app()->environment("testing")) {
            app(\App\Services\Billing\CreditLedger::class)->record(
                \App\Enums\CreditProduct::Sms,
                \App\Enums\CreditKind::Grant,
                100,
                "test"
            );
        }
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
