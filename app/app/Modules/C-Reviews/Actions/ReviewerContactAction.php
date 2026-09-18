<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Enums\OutreachChannel;
use App\Models\Customer;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X121\Actions\EntityReadAction;
use App\Services\Consent\ConsentService;

final class ReviewerContactAction
{
    public function handle(int $businessId, int $reviewRequestId): array
    {
        $req = ReviewRequest::where('business_id', $businessId)->findOrFail($reviewRequestId);

        if ($req->customer_id === null) {
            return [
                'status' => 'refused',
                'refusal_code' => 'REVIEWER_NAME_IS_NOT_CONSENT',
            ];
        }

        $person = app(EntityReadAction::class)->handle('people', (int) $req->customer_id, $businessId);
        if ($person === null || empty($person['phone'])) {
            return ['status' => 'refused', 'refusal_code' => 'NO_CONSENT_RECORD'];
        }

        $customer = Customer::where('business_id', $businessId)->where('phone', $person['phone'])->first();
        if ($customer === null) {
            return ['status' => 'refused', 'refusal_code' => 'NO_CONSENT_RECORD'];
        }

        $decision = app(ConsentService::class)->decide($customer, OutreachChannel::Sms);
        if (! $decision->isGranted()) {
            return ['status' => 'refused', 'refusal_code' => 'CONSENT_REFUSED', 'reason' => $decision->reason->value ?? $decision->reason->name];
        }

        return [
            'status' => 'sent',
            'review_request_id' => $req->id,
        ];
    }
}
