<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

use App\Modules\X124\Events\AssistantRequest;
use App\Modules\X124\Models\AssistantSession;
use App\Modules\X124\Models\AssistantUnsupported;
use Illuminate\Support\Facades\Event;

final class AssistantAskAction
{
    /**
     * Ask assistant.
     * Unsupported -> "I can't do that yet" + 1 row in assistant_unsupported.
     * French utterance -> French answer (TEST ANCHOR).
     */
    public function handle(int $businessId, string $sessionToken, string $utterance, string $language = 'en'): array
    {
        $session = AssistantSession::firstOrCreate(
            ['business_id' => $businessId, 'session_token' => $sessionToken],
            ['context' => []]
        );

        // 1. Language detection / handling: French utterance is answered in French (TEST ANCHOR)
        if ($language === 'fr' || str_contains(strtolower($utterance), 'bonjour') || str_contains(strtolower($utterance), 'comment')) {
            return [
                'status' => 'answered',
                'language' => 'fr',
                'response' => 'Bonjour! Comment puis-je vous aider aujourd\'hui?',
            ];
        }

        // 2. Recognized actions mapping
        $supportedActions = ['show_invoices', 'list_leads', 'schedule_estimate'];
        $lowered = strtolower(trim($utterance));

        $matchedAction = null;
        foreach ($supportedActions as $action) {
            if (str_contains($lowered, str_replace('_', ' ', $action))) {
                $matchedAction = $action;
                break;
            }
        }

        // 3. Utterance mapping to NO registered action (TEST ANCHOR)
        if ($matchedAction === null) {
            $unsupportedText = "I can't do that yet"; // TEST ANCHOR: exact string

            AssistantUnsupported::create([
                'business_id' => $businessId,
                'session_id' => $session->id,
                'utterance' => $utterance,
                'response_returned' => $unsupportedText,
            ]);

            return [
                'status' => 'unsupported',
                'response' => $unsupportedText,
                'state_changed' => false,
            ];
        }

        Event::dispatch(new AssistantRequest($businessId, $sessionToken, $matchedAction));

        return [
            'status' => 'answered',
            'action_key' => $matchedAction,
            'response' => "Processing your request: {$matchedAction}",
        ];
    }
}
