<?php

declare(strict_types=1);

namespace App\Modules\X01\Domain;

use App\Models\Conversation;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X01\Events\LeadScored;
use App\Modules\X01\Events\TakeoverReleased;
use App\Modules\X01\Events\TakeoverStarted;
use App\Modules\X01\Exceptions\LeadRatingOutOfRangeRefused;
use App\Modules\X01\Exceptions\TakeoverNotLatchedRefused;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Models\TakeoverLatch;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X121\Actions\EntityWriteAction;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Services\Config\DefaultsRegistry;
use App\Services\Conversations\ConversationThreads;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * (R245) Integrity: Rethrow invariant-violation InvalidArgumentExceptions in ingestMessage.
 */
final class UnifiedInboxManager
{
    public const TIER_HOT = 80;

    public const TIER_WARM = 60;

    public const TIER_COOL = 40;

    public const TIER_COLD = 20;

    public function __construct(private DefaultsRegistry $defaults) {}

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

            $lookup = app(PersonLookupAction::class);
            $personId = $isEmail ? $lookup->idForEmail($businessId, $identifier) : $lookup->idForPhone($businessId, $identifier);

            if ($personId === null) {
                $personId = $lookup->create($businessId, [
                    'first_name' => $senderName,
                    'last_name' => '',
                    'email' => $isEmail ? $identifier : null,
                    'phone' => ! $isEmail ? $identifier : null,
                ]);

                Event::dispatch(new ContactCreated(
                    businessId: $businessId,
                    personId: $personId,
                    name: $senderName,
                    phone: ! $isEmail ? $identifier : null,
                    email: $isEmail ? $identifier : null
                ));
            } else {
                // Merge identifier if missing
                $person = app(EntityReadAction::class)->handle('people', $personId, $businessId);
                $writer = app(EntityWriteAction::class);
                if ($isEmail && empty($person['email'])) {
                    $writer->handle('people', $personId, $businessId, ['email' => $identifier]);
                } elseif (! $isEmail && empty($person['phone'])) {
                    $writer->handle('people', $personId, $businessId, ['phone' => $identifier]);
                }
            }

            // Find or create Conversation for this Person
            $conversation = Tenancy::actingAs($businessId, function () use ($personId, $channel) {
                $convo = Conversation::firstOrCreate(
                    ['person_id' => $personId],
                    ['channel' => $channel, 'status' => 'open']
                );

                if (in_array($channel, ['whatsapp', 'email'])) {
                    if (! $convo->hasLoggedConsent()) {
                        $convo->consent_logged_at = now();
                        $convo->save();
                    }
                }

                return $convo;
            });

            Event::dispatch(new ConversationUpdated(
                businessId: $businessId,
                conversationId: $conversation->id,
                channel: $channel,
                messageSnippet: substr($body, 0, 50)
            ));

            // Safe to call recordInbound (which relies on the ambient tenant without setting it) because
            // the Person find-or-create above triggers the `people` table's RLS policy (ENABLE + FORCE ROW LEVEL SECURITY
            // with a matching WITH CHECK in 2026_08_30_000001_create_x121_noun_tables.php:203). If the ambient
            // tenant is absent or mismatched, the Person write fails before reaching here.
            try {
                app(ConversationThreads::class)->recordInbound($conversation, $body);
            } catch (\InvalidArgumentException $e) {
                if (str_contains($e->getMessage(), 'cleared to store message content')) {
                    // A thread that has not been cleared to store message content does not store one, dropping it instead.
                } else {
                    throw $e;
                }
            }

            return [
                'person_id' => $personId,
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
     * Release human takeover on a conversation (TEST ANCHOR).
     */
    public function releaseTakeover(int $businessId, int $conversationId): array
    {
        return DB::transaction(function () use ($businessId, $conversationId) {
            $latch = TakeoverLatch::where('business_id', $businessId)
                ->where('conversation_id', $conversationId)
                ->where('is_active', true)
                ->first();

            if ($latch === null) {
                throw TakeoverNotLatchedRefused::forConversation($conversationId);
            }

            $latch->update([
                'is_active' => false,
                'released_at' => now(),
            ]);

            Event::dispatch(new TakeoverReleased(
                businessId: $businessId,
                conversationId: $conversationId
            ));

            return [
                'latch_id' => $latch->id,
                'conversation_id' => $conversationId,
                'is_active' => false,
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

        if ($latch === null) {
            throw TakeoverNotLatchedRefused::forConversation($conversationId);
        }

        return [
            'conversation_id' => $conversationId,
            'operator_name' => $latch->operator_name,
            'label' => 'Human takeover',
            'body' => $body,
            'formatted_reply' => "[Human takeover by {$latch->operator_name}]: {$body}",
        ];
    }

    /**
     * Calculate and record lead score (G2-32, G2-38, G2-61).
     */
    public function scoreLead(int $businessId, int $personId, int $score): LeadScore
    {
        if ($score < 0 || $score > 100) {
            throw LeadRatingOutOfRangeRefused::forRating($score);
        }

        $grade = $this->gradeFor($score);
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

    private function gradeFor(int $rating): string
    {
        if ($rating >= $this->defaults->int('crm.lead_score.tier_hot')) {
            return 'A';
        }
        if ($rating >= $this->defaults->int('crm.lead_score.tier_warm')) {
            return 'B';
        }
        if ($rating >= $this->defaults->int('crm.lead_score.tier_cool')) {
            return 'C';
        }
        if ($rating >= $this->defaults->int('crm.lead_score.tier_cold')) {
            return 'D';
        }

        return 'F';
    }
}
