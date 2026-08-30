<?php

declare(strict_types=1);

namespace App\Modules\X220\Actions;

use App\Modules\X220\Models\AiPrompt;

final class PromptResolveAction
{
    public function handle(int $businessId, string $promptKey, ?int $version = null): ?AiPrompt
    {
        $query = AiPrompt::where('business_id', $businessId)->where('prompt_key', $promptKey);

        if ($version !== null) {
            return $query->where('version', $version)->first();
        }

        // Return latest frozen or latest created
        return $query->orderBy('version', 'desc')->first();
    }
}
