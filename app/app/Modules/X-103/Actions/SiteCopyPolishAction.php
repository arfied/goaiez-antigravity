<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;

final class SiteCopyPolishAction
{
    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function handle(int $businessId, int $pageId): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        $maxChars = $this->registry->int('sites.copy.max_chars');
        $systemPrompt = $this->registry->string('sites.copy.system_prompt');
        $systemPrompt = str_replace('{max_chars}', (string) $maxChars, $systemPrompt);

        $blocks = $page->draft_blocks ?? [];
        $polishedCount = 0;
        $totalCost = 0;
        $lastModel = null;

        foreach ($blocks as $i => $block) {
            if (! in_array($block['type'] ?? '', ['hero', 'about'])) {
                continue;
            }

            $text = $block['text'] ?? '';
            if (empty($text)) {
                continue;
            }

            $response = $this->router->dispatch(new AiRequest(
                task: AiTask::SiteCopy,
                prompt: $text,
                system: $systemPrompt,
            ));

            if ($response->refused || $response->failureReason !== null) {
                return [
                    'status' => 'refused',
                    'reason' => $response->refused ? 'model_refused' : $response->failureReason,
                    'model' => $response->model->value,
                ];
            }

            $blocks[$i]['original_text'] = $text;
            $blocks[$i]['text'] = $response->text;
            $blocks[$i]['source'] = 'ai';
            $blocks[$i]['model'] = $response->model->value;

            $lastModel = $response->model->value;
            $totalCost += $response->costInHundredthsOfCents();
            $polishedCount++;
        }

        if ($polishedCount > 0) {
            $page->draft_blocks = $blocks;
            $page->save();
        }

        return [
            'status' => 'polished',
            'blocks' => $polishedCount,
            'model' => $lastModel,
            'cost_hundredths' => $totalCost,
        ];
    }
}
