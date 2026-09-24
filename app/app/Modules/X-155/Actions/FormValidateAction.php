<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X155\Events\FormSpamRejected;
use App\Modules\X155\Models\FormDefinition;
use Illuminate\Support\Facades\Event;

final class FormValidateAction
{
    public function handle(
        int $businessId,
        int $formDefinitionId,
        array $payload,
        ?string $ipAddress = null,
        ?string $userTimezone = null
    ): array {
        $form = FormDefinition::where('business_id', $businessId)->findOrFail($formDefinitionId);

        // 1. Honeypot bot check (G3-64, G13-05)
        $configured = $form->honeypot_field ?? '';
        $honeypot = trim((string) $configured) !== '' ? (string) $configured : 'website_url';
        $submitted = $payload[$honeypot] ?? null;
        if ($submitted !== null && $submitted !== '' && $submitted !== []) {
            Event::dispatch(new FormSpamRejected($businessId, $formDefinitionId, 'honeypot_triggered', $ipAddress));

            return [
                'is_valid' => false,
                'is_spam' => true,
                'reason' => 'honeypot_triggered',
            ];
        }

        // 2. IP vs Timezone bot signal check (G17-12)
        if ($userTimezone !== null && ! in_array($userTimezone, timezone_identifiers_list(), true)) {
            Event::dispatch(new FormSpamRejected($businessId, $formDefinitionId, 'ip_timezone_mismatch', $ipAddress));

            return [
                'is_valid' => false,
                'is_spam' => true,
                'reason' => 'ip_timezone_mismatch',
            ];
        }

        // 3. Multi-step logic (G2-17), narrowed by the adaptive rule (G5-30):
        //    a step the answers do not reach is never enforced.
        $steps = (new FormAdaptiveStepsAction)->handle($form, $payload);

        foreach ($steps as $index => $step) {
            $required = $step['required'] ?? [];

            if (! is_array($required) || $required === []) {
                continue;
            }

            $missing = [];

            foreach ($required as $field) {
                // a member that is not a valid array key is not a field name, so it is skipped exactly as a malformed required is at :54
                if (! is_string($field) && ! is_int($field)) {
                    continue;
                }

                if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                    $missing[] = $field;
                }
            }

            if ($missing !== []) {
                return [
                    'is_valid' => false,
                    'is_spam' => false,
                    'reason' => 'incomplete_step',
                    'step' => $step['step'] ?? $index + 1,
                    'missing' => $missing,
                ];
            }
        }

        return [
            'is_valid' => true,
            'is_spam' => false,
        ];
    }
}
