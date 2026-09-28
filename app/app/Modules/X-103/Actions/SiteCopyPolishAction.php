<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResponse;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Facts\BusinessFacts;
use App\Services\Industry\IndustryQuestions;
use App\Services\Visibility\CompetitorSiteNotes;

final class SiteCopyPolishAction
{
    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly CompetitorSiteNotes $peers,
        private readonly BusinessFacts $facts,
        private readonly IndustryQuestions $questions
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

        $reference = $this->peers->referenceBlock($businessId);
        $peerCount = $reference === '' ? 0 : count($this->peers->notesFor($businessId));

        $stated = $this->facts->all($businessId);
        $industryQuestions = $this->questions->forBusiness($businessId);
        $ownerFacts = [];
        foreach ($industryQuestions as $key => $def) {
            if (($stated[$key] ?? '') !== '') {
                $ownerFacts[] = "{$def['label']}: {$stated[$key]}";
            }
        }

        foreach ($blocks as $i => $block) {
            if (! in_array($block['type'] ?? '', ['hero', 'about'])) {
                continue;
            }

            $text = $block['text'] ?? '';
            if (! empty($text)) {
                $response = $this->polishOne($text, $reference, $ownerFacts, $systemPrompt);
                if (is_array($response)) {
                    return $response;
                }

                $blocks[$i]['original_text'] = $text;
                $blocks[$i]['text'] = $response->text;
                $blocks[$i]['source'] = 'ai';
                $blocks[$i]['model'] = $response->model->value;
                if ($peerCount > 0) {
                    $blocks[$i]['peers'] = $peerCount;
                }

                $lastModel = $response->model->value;
                $totalCost += $response->costInHundredthsOfCents();
                $polishedCount++;
            }

            if (($block['type'] ?? '') === 'hero') {
                $subline = (string) ($block['subline'] ?? '');
                if (trim($subline) !== '') {
                    $response = $this->polishOne($subline, $reference, $ownerFacts, $systemPrompt);
                    if (is_array($response)) {
                        return $response;
                    }

                    $blocks[$i]['original_subline'] = $subline;
                    $blocks[$i]['subline'] = $response->text;
                    $blocks[$i]['source'] = 'ai';
                    $blocks[$i]['model'] = $response->model->value;
                    if ($peerCount > 0) {
                        $blocks[$i]['peers'] = $peerCount;
                    }

                    $lastModel = $response->model->value;
                    $totalCost += $response->costInHundredthsOfCents();
                    $polishedCount++;
                }
            }
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
            'peers' => $peerCount,
        ];
    }

    private function polishOne(string $text, string $reference, array $ownerFacts, string $systemPrompt): AiResponse|array
    {
        $prompt = "Text to rewrite:\n{$text}".($reference === '' ? '' : "\n\n".$reference);
        if ($ownerFacts !== []) {
            $prompt .= "\n\nFacts the owner stated (use only these; do not add any):\n".implode("\n", $ownerFacts);
        }

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteCopy,
            prompt: $prompt,
            system: $systemPrompt,
        ));

        if ($response->refused || $response->failureReason !== null) {
            return [
                'status' => 'refused',
                'reason' => $response->refused ? 'model_refused' : $response->failureReason,
                'model' => $response->model->value,
            ];
        }

        return $response;
    }
}
