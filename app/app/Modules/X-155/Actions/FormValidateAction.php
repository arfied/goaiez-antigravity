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
        $honeypot = $form->honeypot_field ?? 'website_url';
        if (! empty($payload[$honeypot])) {
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

        return [
            'is_valid' => true,
            'is_spam' => false,
        ];
    }
}
