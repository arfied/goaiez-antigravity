<?php

declare(strict_types=1);

namespace App\Modules\X220\Actions;

use App\Modules\X220\Models\AiPrompt;

final class PromptRegisterAction
{
    public function handle(int $businessId, string $promptKey, string $jobClass, string $body): AiPrompt
    {
        $latest = AiPrompt::where('business_id', $businessId)
            ->where('prompt_key', $promptKey)
            ->orderBy('version', 'desc')
            ->first();

        if ($latest !== null && hash('sha256', $latest->body) === hash('sha256', $body)) {
            return $latest;
        }

        return AiPrompt::create([
            'business_id' => $businessId,
            'prompt_key' => $promptKey,
            'version' => $latest ? $latest->version + 1 : 1,
            'body' => $body,
            'job_class' => $jobClass,
            'created_by' => 'system',
        ]);
    }
}
