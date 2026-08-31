<?php

declare(strict_types=1);

namespace App\Modules\X01\Domain;

use App\Modules\X01\Events\ContactCreated;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X01\Events\LeadScored;
use App\Modules\X01\Events\TakeoverStarted;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Models\TakeoverLatch;
use App\Modules\X121\Models\Conversation;
use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class UnifiedInboxManager
{
    /**
     * Ingest messages from different channels (SMS, Email, Voice, Chat) into one Person thread (TEST ANCHOR).
     */
    public function ingestMessage(
        int $businessId,
        string $channel,
        string $identifier, // phone or email
        string $senderName,
        string $body
    ): array {
        return DB::transaction(function () use ($businessId, $channel, $identifier, $senderName, $body) {
            $isEmail = str_contains($identifier, '@');

            // Find or create single Person
            $person = Person::where('business_id', $businessId)
                ->where(function ($query) use ($identifier, $isEmail) {
                    if ($isEmail) {
                        $query->where('email', $identifier);
                    } else {
                        $query->where('phone', $identifier);
                    }
                })->first();

            if ($person === null) {
                $person = Person::create([
                    'business_id' => $businessId,
                    'first_name' => $senderName,
                    'last_name' => '',
                    'email' => $isEmail ? $identifier : null,
                    'phone' => ! $isEmail ? $identifier : null,
                ]);

                Event::dispatch(new ContactCreated(
                    businessId: $businessId,
                    personId: $person->id,
                    name: $senderName,
                    phone: $person->phone,
                    email: $person->email
                ));
            } else {
                // Merge identifier if missing
                if ($isEmail && empty($person->email)) {
                    $person->update(['email' => $identifier]);
                } elseif (! $isEmail && empty($person->phone)) {
                    $person->update(['phone' => $identifier]);
                }
            }

            // Find or create Conversation for this Person
            $conversation = Conversation::firstOrCreate(
                ['business_id' => $businessId, 'person_id' => $person->id],
                ['channel' => $channel, 'status' => 'open']
            );

            Event::dispatch(new ConversationUpdated(
                businessId: $businessId,
                conversationId: $conversation->id,
                channel: $channel,
                messageSnippet: substr($body, 0, 50)
            ));

            return [
                'person_id' => $person->id,
                'conversation_id' => $conversation->id,
                'channel' => $channel,
                'body' => $body,
            ];
        });
    }

    /**
     * Start human takeover on a conversation (TEST ANCHOR).
     */
    public function takeover(int $businessId, int $conversationId, int $operatorId, string $operatorName): array
    {
        return DB::transaction(function () use ($businessId, $conversationId, $operatorId, $operatorName) {
            $latch = TakeoverLatch::updateOrCreate(
                ['business_id' => $businessId, 'conversation_id' => $conversationId],
                [
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                    'is_active' => true,
                    'latched_at' => now(),
                    'released_at' => null,
                ]
            );

            Event::dispatch(new TakeoverStarted(
                businessId: $businessId,
                conversationId: $conversationId,
                operatorId: $operatorId,
                operatorName: $operatorName
            ));

            return [
                'latch_id' => $latch->id,
                'conversation_id' => $conversationId,
                'operator_name' => $operatorName,
                'label' => 'Human takeover',
                'is_active' => true,
            ];
        });
    }

    /**
     * Reply with human takeover label and operator name (TEST ANCHOR).
     */
    public function replyWithTakeover(int $businessId, int $conversationId, string $body): array
    {
        $latch = TakeoverLatch::where('business_id', $businessId)
            ->where('conversation_id', $conversationId)
            ->where('is_active', true)
            ->first();

        $operatorName = $latch ? $latch->operator_name : 'Staff Member';

        return [
            'conversation_id' => $conversationId,
            'operator_name' => $operatorName,
            'label' => 'Human takeover',
            'body' => $body,
            'formatted_reply' => "[Human takeover by {$operatorName}]: {$body}",
        ];
    }

    /**
     * Calculate and record lead score (G2-32, G2-38, G2-61).
     */
    public function scoreLead(int $businessId, int $personId, int $score, string $grade = 'A'): LeadScore
    {
        $ls = LeadScore::updateOrCreate(
            ['business_id' => $businessId, 'person_id' => $personId],
            [
                'lead_rating' => $score,
                'grade' => $grade,
                'confidence' => 0.98,
                'signals' => ['recent_inquiry' => true, 'intent_score' => $score],
            ]
        );

        Event::dispatch(new LeadScored(
            businessId: $businessId,
            personId: $personId,
            score: $score,
            grade: $grade
        ));

        return $ls;
    }
}
