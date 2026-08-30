<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

final class AssistantExplainAction
{
    public function handle(int $businessId, string $featureKey): string
    {
        $template = __('assistant.feature_explanation', ['feature' => $featureKey]);

        return ! empty($template) && $template !== 'assistant.feature_explanation'
            ? (string) $template
            : "feature: {$featureKey}";
    }
}
