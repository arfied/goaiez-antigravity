<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

use App\Modules\X124\Events\AssistantActed;
use App\Modules\X124\Models\AssistantRecommendation;

final class AssistantActOnRecommendationAction
{
    public function handle(int $businessId, int $recommendationId, string $action): void
    {
        $rec = AssistantRecommendation::where('business_id', $businessId)
            ->findOrFail($recommendationId);
            
        $rec->update(['status' => $action]); // 'accepted' or 'dismissed'
        AssistantActed::dispatch($businessId, $rec->id, $action);
    }
}
