<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Events\ChatLeadCaptured;
use App\Modules\X102\Models\ChatLead;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Models\ChatTurn;
use App\Modules\X121\Actions\PersonUpsertAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class ChatCaptureAction
{
    public function handle(
        int $businessId,
        int $sessionId,
        string $name,
        string $phone,
        ?string $email = null,
        ?string $message = null,
        string $formType = 'live_chat',
        bool $consent = false
    ): ChatLead {
        if (trim($phone) === '') {
            throw new \DomainException('NO_CONTACT_METHOD_ON_CAPTURE');
        }

        if (! $consent) {
            $message = null;
        }

        // A detail that is blank or whitespace was not given (R245, 2026-09-05).
        $message = is_string($message) && trim($message) === '' ? null : $message;

        return DB::transaction(function () use ($businessId, $sessionId, $name, $phone, $email, $message, $formType, $consent) {
            $session = ChatSession::where('business_id', $businessId)->findOrFail($sessionId);

            // P-148 (GOAIEZ-MASTER-PLAN.md:625, row 30484): an under-18 signal at ingest prevents the
            // contact row, asserted at the write, not at the reply. On the chat door the signal arrives
            // on a turn: C-Agent refuses it as UNDER_18 and ChatTurnAction records that code on the agent
            // turn, so the refusal is read here, before any Person or ChatLead is written.
            if (ChatTurn::where('business_id', $businessId)
                ->where('chat_session_id', $session->id)
                ->where('refusal_code', 'UNDER_18')
                ->exists()) {
                throw new \DomainException('UNDER_18_SIGNAL_ON_SESSION');
            }

            // 1. Form submission creates the Person (TEST ANCHOR)
            // A detail that is blank or whitespace was not given (R245, 2026-09-05).
            $contactEmail = is_string($email) && trim($email) === '' ? null : $email;

            $upsert = app(PersonUpsertAction::class)->upsertByPhone(
                $businessId,
                $phone,
                [
                    'first_name' => $name,
                    'email' => $contactEmail,
                ]
            );

            $lead = ChatLead::create([
                'business_id' => $businessId,
                'chat_session_id' => $session->id,
                'person_id' => $upsert['id'],
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'message' => $message,
                'form_type' => $formType,
                // (R245) schema: added consent_logged_at to chat_leads table to log capture consent
                'consent_logged_at' => $consent ? now() : null,
            ]);

            $session->update(['status' => 'lead_captured']);

            Event::dispatch(new ChatLeadCaptured(
                businessId: $businessId,
                leadId: $lead->id,
                personId: $upsert['id'],
                name: $name,
                phone: $phone,
                message: $message
            ));

            return $lead;
        });
    }
}
