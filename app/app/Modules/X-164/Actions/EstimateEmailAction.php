<?php

declare(strict_types=1);

namespace App\Modules\X164\Actions;

use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\EstimateEmailSender;

final class EstimateEmailAction
{
    public function handle(int $estimateId): array
    {
        $sender = app(EstimateEmailSender::class);
        $recipient = $sender->recipientFor($estimateId);

        if ($recipient['refusal'] !== null) {
            return ['sent' => false, 'message' => $recipient['refusal']];
        }

        $customer = $recipient['customer'];

        $decision = app(ConsentService::class)->decide(
            $customer,
            OutreachChannel::Email,
            OutreachPurpose::Transactional
        );

        if (! $decision->isGranted()) {
            return ['sent' => false, 'message' => 'Not sent — email to this customer is not allowed right now: '.$decision->reason->ownerSentence(OutreachChannel::Email).'.'];
        }

        return $sender->deliver($estimateId, $customer, $decision->permit);
    }
}
