<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

use App\Modules\X124\Models\AssistantRecommendation;

final class AssistantRecommendAction
{
    public function handle(int $businessId, int $sessionId, string $title, string $actionKey): AssistantRecommendation
    {
        return AssistantRecommendation::create([
            'business_id' => $businessId,
            'session_id' => $sessionId,
            'title' => $title,
            'action_key' => $actionKey,
            'status' => 'active',
        ]);
    }
}
