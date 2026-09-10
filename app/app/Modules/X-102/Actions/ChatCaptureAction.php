<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Events\ChatLeadCaptured;
use App\Modules\X102\Models\ChatLead;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X121\Models\Person;
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
        bool $consent = true
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

            // 1. Form submission creates the Person (TEST ANCHOR)
            // A detail that is blank or whitespace was not given (R245, 2026-09-05).
            $contactEmail = is_string($email) && trim($email) === '' ? null : $email;

            $person = Person::updateOrCreate(
                ['business_id' => $businessId, 'phone' => $phone],
                array_filter([
                    'first_name' => $name,
                    'email' => $contactEmail,
                ], fn ($v) => $v !== null)
            );

            $lead = ChatLead::create([
                'business_id' => $businessId,
                'chat_session_id' => $session->id,
                'person_id' => $person->id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'message' => $message,
                'form_type' => $formType,
                'consent_logged_at' => $consent ? now() : null,
            ]);

            $session->update(['status' => 'lead_captured']);

            Event::dispatch(new ChatLeadCaptured(
                businessId: $businessId,
                leadId: $lead->id,
                personId: $person->id,
                name: $name,
                phone: $phone,
                message: $message
            ));

            return $lead;
        });
    }
}
// (R245) schema: added consent_logged_at to chat_leads table to log capture consent
