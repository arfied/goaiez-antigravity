<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Domain;

use App\Modules\CWhatsapp\Events\TemplateApproved;
use App\Modules\CWhatsapp\Events\WhatsappSent;
use App\Modules\CWhatsapp\Events\WhatsappSessionOpened;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Modules\X204\Domain\ConsentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class WhatsappEngine
{
    public function __construct(
        private readonly ConsentService $consentService
    ) {}

    public function recordInbound(
        int $businessId,
        string $recipientPhone,
        string $body = '',
        string $senderName = '',
        ?string $zernioConversationId = null,
        ?string $participantRef = null,
        ?string $inboundRef = null
    ): WhatsappSession {
        $session = null;
        if ($participantRef !== null) {
            $session = WhatsappSession::where('business_id', $businessId)
                ->where('zernio_participant_ref', $participantRef)
                ->first();
        }

        $now = Carbon::now();
        $attributes = [
            'last_inbound_at' => $now,
            'session_window_expires_at' => $now->copy()->addHours(24),
            'is_window_open' => true,
        ];

        if ($zernioConversationId !== null) {
            $attributes['zernio_conversation_id'] = $zernioConversationId;
        }
        if ($participantRef !== null) {
            $attributes['zernio_participant_ref'] = $participantRef;
        }
        if ($inboundRef !== null) {
            $attributes['last_inbound_ref'] = $inboundRef;
        }

        if ($session !== null) {
            if ($recipientPhone !== '') {
                $attributes['recipient_phone'] = $recipientPhone;
            }
            $session->update($attributes);
        } else {
            $session = WhatsappSession::updateOrCreate(
                ['business_id' => $businessId, 'recipient_phone' => $recipientPhone],
                $attributes
            );
        }

        if ($recipientPhone === '') {
            return $session;
        }

        Event::dispatch(new WhatsappSessionOpened($businessId, $session->id, $recipientPhone, $body, $senderName));

        return $session;
    }

    /**
     * Send WhatsApp message respecting 24h window and template constraints (TEST ANCHOR).
     */
    public function send(
        int $businessId,
        string $recipientPhone,
        string $messageText,
        ?string $templateName = null,
        string $class = 'transactional'
    ): array {
        $decision = $this->consentService->decide($businessId, $recipientPhone, 'whatsapp', $class);
        if (! $decision['granted']) {
            $reason = $decision['reason'];

            return [
                'status' => 'refused',
                'refusal_code' => $reason,
                'message' => 'Send suppressed due to consent check: '.$decision['reason'],
            ];
        }

        $session = WhatsappSession::where('business_id', $businessId)
            ->where('recipient_phone', $recipientPhone)
            ->first();

        $isInside24hWindow = false;
        if ($session !== null && $session->last_inbound_at !== null) {
            $isInside24hWindow = $session->last_inbound_at->greaterThan(Carbon::now()->subHours(24));
        }

        // 1. If send is within 24h of last inbound (e.g. 23 hours) -> free-form send succeeds (TEST ANCHOR)
        if ($isInside24hWindow) {
            Event::dispatch(new WhatsappSent($businessId, $recipientPhone, 'free_form'));

            return [
                'status' => 'sent',
                'mode' => 'free_form',
                'recipient_phone' => $recipientPhone,
                'message' => $messageText,
            ];
        }

        // 2. If send is outside 24h window (e.g. 25 hours): MUST have an approved template (TEST ANCHOR)
        if (empty($templateName)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'OUTSIDE_24H_WINDOW_TEMPLATE_REQUIRED',
                'message' => 'Cannot send free-form message outside the 24-hour customer service window without an approved template',
            ];
        }

        $template = WhatsappTemplate::where('business_id', $businessId)
            ->where('name', $templateName)
            ->where('status', 'approved')
            ->first();

        if ($template === null) {
            return [
                'status' => 'refused',
                'refusal_code' => 'TEMPLATE_NOT_APPROVED',
                'message' => "The specified template '{$templateName}' is not approved by Meta",
            ];
        }

        // Outside 24h with approved template -> template send succeeds
        Event::dispatch(new WhatsappSent($businessId, $recipientPhone, 'template'));

        return [
            'status' => 'sent',
            'mode' => 'template',
            'template_name' => $templateName,
            'recipient_phone' => $recipientPhone,
        ];
    }

    /**
     * Submit and approve template.
     */
    public function approveTemplate(int $businessId, int $templateId): WhatsappTemplate
    {
        $template = WhatsappTemplate::where('business_id', $businessId)->findOrFail($templateId);
        $template->update([
            'status' => 'approved',
        ]);

        Event::dispatch(new TemplateApproved($businessId, $template->id, $template->name));

        return $template;
    }
}
