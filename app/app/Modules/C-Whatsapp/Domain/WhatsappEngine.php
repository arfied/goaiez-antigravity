<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Domain;

use App\Modules\CWhatsapp\Actions\WhatsappConnectionLookupAction;
use App\Modules\CWhatsapp\Events\TemplateApproved;
use App\Modules\CWhatsapp\Events\WhatsappSent;
use App\Modules\CWhatsapp\Events\WhatsappSessionOpened;
use App\Modules\CWhatsapp\Models\WhatsappDelivery;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Modules\X204\Domain\ConsentService;
use App\Services\Zernio\ZernioSendReceipt;
use App\Services\Zernio\ZernioWhatsappClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class WhatsappEngine
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly WhatsappConnectionLookupAction $connectionLookup,
        private readonly ZernioWhatsappClient $whatsappClient
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
        string $class = 'transactional',
        array $templateParams = []
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

        $conn = $this->connectionLookup->forBusiness($businessId);
        if ($conn === null || $conn->status !== 'connected') {
            return [
                'status' => 'refused',
                'refusal_code' => 'WHATSAPP_NOT_CONNECTED',
                'message' => 'Connect a WhatsApp number first — nothing was sent.',
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
            if ($session->zernio_conversation_id === null) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'NO_ZERNIO_CONVERSATION',
                    'message' => 'The customer has not written to this number through Zernio yet — nothing was sent.',
                ];
            }

            $row = WhatsappDelivery::create([
                'business_id' => $businessId,
                'whatsapp_session_id' => $session->id,
                'mode' => 'free_form',
                'status' => 'sending',
                'zernio_conversation_id' => $session->zernio_conversation_id,
            ]);

            $receipt = $this->whatsappClient->replyInConversation(
                $session->zernio_conversation_id,
                $conn->account_ref,
                $messageText,
                'whatsapp-message-'.$row->id
            );

            return $this->processReceipt($receipt, $row, $session, $recipientPhone, 'free_form', $messageText, null);
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

        $row = WhatsappDelivery::create([
            'business_id' => $businessId,
            'whatsapp_session_id' => $session?->id,
            'mode' => 'template',
            'template_name' => $templateName,
            'status' => 'sending',
        ]);

        $receipt = $this->whatsappClient->openWithTemplate(
            $conn->account_ref,
            ltrim(preg_replace('/\D/', '', $recipientPhone), '0'),
            $template->name,
            $template->language,
            $templateParams
        );

        if ($receipt->conversationId !== null && $session !== null) {
            $session->update(['zernio_conversation_id' => $receipt->conversationId]);
        }

        return $this->processReceipt($receipt, $row, $session, $recipientPhone, 'template', $messageText, $templateName);
    }

    private function processReceipt(
        ZernioSendReceipt $receipt,
        WhatsappDelivery $row,
        ?WhatsappSession $session,
        string $recipientPhone,
        string $mode,
        string $messageText,
        ?string $templateName
    ): array {
        if ($receipt->outcome === 'sent') {
            $row->update([
                'status' => 'sent',
                'provider_message_ref' => $receipt->messageRef,
            ]);
            Event::dispatch(new WhatsappSent($row->business_id, $recipientPhone, $mode, $receipt->messageRef));
            $res = [
                'status' => 'sent',
                'mode' => $mode,
                'recipient_phone' => $recipientPhone,
                'message' => $messageText,
                'provider_message_ref' => $receipt->messageRef,
            ];
            if ($templateName !== null) {
                $res['template_name'] = $templateName;
            }

            return $res;
        }

        if ($receipt->outcome === 'unknown') {
            $row->update(['status' => 'unconfirmed']);
            $res = [
                'status' => 'unconfirmed',
                'mode' => $mode,
                'recipient_phone' => $recipientPhone,
                'message' => $messageText,
                'provider_message_ref' => null,
            ];
            if ($templateName !== null) {
                $res['template_name'] = $templateName;
            }

            return $res;
        }

        if ($receipt->outcome === 'refused' && $receipt->code === 'window_closed') {
            $row->update(['status' => 'refused']);
            $session?->update(['is_window_open' => false]);

            return [
                'status' => 'refused',
                'refusal_code' => 'OUTSIDE_24H_WINDOW_TEMPLATE_REQUIRED',
                'message' => 'Cannot send free-form message outside the 24-hour customer service window without an approved template',
            ];
        }

        $row->update([
            'status' => 'failed',
            'error_code' => $receipt->code,
        ]);

        $res = [
            'status' => 'failed',
            'mode' => $mode,
            'recipient_phone' => $recipientPhone,
            'message' => $messageText,
            'provider_message_ref' => null,
            'error_code' => $receipt->code,
        ];
        if ($templateName !== null) {
            $res['template_name'] = $templateName;
        }

        return $res;
    }

    public function applyTemplateStatus(int $businessId, ?string $providerRef, string $name, string $language, string $zernioStatus, ?string $reason): ?WhatsappTemplate
    {
        $template = null;
        if ($providerRef !== null) {
            $template = WhatsappTemplate::where('business_id', $businessId)
                ->where('provider_template_ref', $providerRef)
                ->first();
        }

        if ($template === null) {
            $template = WhatsappTemplate::where('business_id', $businessId)
                ->where('name', $name)
                ->where('language', $language)
                ->first();
        }

        if ($template === null) {
            return null;
        }

        $new = TemplateStatuses::fromZernio($zernioStatus);

        if ($new === null) {
            return $template;
        }

        $oldStatus = $template->status;

        $updates = [
            'status' => $new,
            'status_reason' => $reason === 'NONE' ? null : $reason,
            'status_updated_at' => Carbon::now(),
        ];
        if (empty($template->provider_template_ref) && $providerRef !== null) {
            $updates['provider_template_ref'] = $providerRef;
        }

        $template->update($updates);

        if ($new === 'approved' && $oldStatus !== 'approved') {
            Event::dispatch(new TemplateApproved($businessId, $template->id, $template->name));
        }

        return $template;
    }

    /**
     * Submit and approve template.
     */
    public function approveTemplate(int $businessId, int $templateId): WhatsappTemplate
    {
        $template = WhatsappTemplate::where('business_id', $businessId)->findOrFail($templateId);
        $this->applyTemplateStatus($businessId, null, $template->name, $template->language, 'APPROVED', null);

        return $template->refresh();
    }
}
