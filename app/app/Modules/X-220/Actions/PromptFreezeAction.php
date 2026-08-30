<?php

declare(strict_types=1);

namespace App\Modules\X220\Actions;

use App\Modules\X220\Events\PromptFrozen;
use App\Modules\X220\Models\AiPrompt;
use Illuminate\Support\Facades\Event;

final class PromptFreezeAction
{
    /**
     * Freeze prompt version (N-238-04: frozen prompt is immutable).
     */
    public function handle(int $businessId, int $promptId): AiPrompt
    {
        $prompt = AiPrompt::where('business_id', $businessId)->findOrFail($promptId);

        $prompt->update(['frozen_at' => now()]);

        Event::dispatch(new PromptFrozen(
            businessId: $businessId,
            promptId: $prompt->id,
            promptKey: $prompt->prompt_key,
            version: $prompt->version
        ));

        return $prompt;
    }
}
