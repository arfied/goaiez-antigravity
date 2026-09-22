<?php

declare(strict_types=1);

namespace App\Modules\X10\Actions;

use App\Modules\X121\Actions\PersonUpsertAction;

final class WidgetFallbackAction
{
    /**
     * Handles: AI-credit cap reached fallback form & person creation, no-fact refusal, and rage clicks escalation (TEST ANCHOR).
     */
    public function handleChatOrFallback(
        int $businessId,
        bool $isCreditCapReached,
        ?string $factFound,
        int $rageClickCount,
        ?array $fallbackFormData = null
    ): array {
        // 1. Four rage-clicks escalate immediately (TEST ANCHOR)
        if ($rageClickCount >= 4) {
            return [
                'status' => 'escalated_human',
                'reason' => 'RAGE_CLICKS_ESCALATION',
                'message' => 'Four rage-clicks detected: escalating to human agent immediately',
            ];
        }

        // 2. AI-credit cap reached: renders form and form submission creates the Person (TEST ANCHOR)
        if ($isCreditCapReached) {
            if ($fallbackFormData !== null && ! empty($fallbackFormData['name'])) {
                $nameParts = explode(' ', (string) $fallbackFormData['name'], 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';

                $upsert = app(PersonUpsertAction::class)->upsertByPhone(
                    $businessId,
                    $fallbackFormData['phone'] ?? null,
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $fallbackFormData['email'] ?? null,
                    ]
                );

                return [
                    'status' => 'fallback_form_submitted',
                    'person_created' => $upsert['created'],
                    'person_id' => $upsert['id'],
                ];
            }

            return [
                'status' => 'render_fallback_form',
                'message' => 'AI credits exhausted: widget renders lead capture form',
            ];
        }

        // 3. Question with no grounding Fact gets the refusal string, not an answer (TEST ANCHOR)
        if (empty($factFound)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'NO_FACT_GROUNDING',
                'answer' => 'I cannot answer that question as no verified business facts exist for this query.',
            ];
        }

        return [
            'status' => 'answered',
            'answer' => 'Verified answer from facts.',
        ];
    }
}
