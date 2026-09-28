<?php

declare(strict_types=1);

namespace App\Modules\X142\Listeners;

use App\Modules\X01\Events\ContactCreated;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X142\Actions\WebhookDispatchAction;
use App\Modules\X164\Events\EstimateAccepted;
use App\Modules\X164\Events\EstimateSent;

class DispatchWebhooksForDomainEvents
{
    public function onContactCreated(ContactCreated $e): void
    {
        app(WebhookDispatchAction::class)->dispatch($e->businessId, 'contact.created', [
            'person_id' => $e->personId,
            'name' => $e->name,
            'phone' => $e->phone,
            'email' => $e->email,
        ]);
    }

    public function onMessageReceived(ConversationUpdated $e): void
    {
        app(WebhookDispatchAction::class)->dispatch($e->businessId, 'message.received', [
            'conversation_id' => $e->conversationId,
            'channel' => $e->channel,
        ]);
    }

    public function onLeadAssigned(LeadAssigned $e): void
    {
        app(WebhookDispatchAction::class)->dispatch($e->businessId, 'lead.assigned', [
            'lead_id' => $e->leadId,
            'assigned_user_id' => $e->staffId,
            'reason' => $e->reason,
        ]);
    }

    public function onEstimateSent(EstimateSent $e): void
    {
        app(WebhookDispatchAction::class)->dispatch($e->businessId, 'estimate.sent', [
            'estimate_id' => $e->estimateId,
            'estimate_number' => $e->estimateNumber,
            'total_cents' => $e->totalCents,
        ]);
    }

    public function onEstimateAccepted(EstimateAccepted $e): void
    {
        app(WebhookDispatchAction::class)->dispatch($e->businessId, 'estimate.accepted', [
            'estimate_id' => $e->estimateId,
            'signed_by' => $e->signedBy,
        ]);
    }
}
