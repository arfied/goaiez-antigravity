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

    public function handle(int $businessId, int $pageId, bool $crawledOnly = false): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        $maxChars = $this->registry->int('sites.copy.max_chars');
        $promptTemplate = $this->registry->string('sites.copy.system_prompt');
        $systemPrompt = str_replace('{max_chars}', (string) $maxChars, $promptTemplate);
        // A subline is one line under the headline, not a paragraph: its own budget, and one sentence.
        $sublinePrompt = str_replace('{max_chars}', (string) min($maxChars, 160), $promptTemplate).' Write exactly one sentence.';

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

            // An automatic polish after a build rewrites only crawled text, never words the owner typed.
            if ($crawledOnly && ($block['source'] ?? '') !== 'inventory') {
                continue;
            }

            $text = $block['text'] ?? '';
            if (! empty($text)) {
                $response = $this->polishOne($text, $reference, $ownerFacts, $systemPrompt);
                if (is_array($response)) {
                    return $response;
                }

                $blocks[$i]['original_text'] = $text;
                $blocks[$i]['text'] = self::plain($response->text);
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
                    $response = $this->polishOne($subline, $reference, $ownerFacts, $sublinePrompt);
                    if (is_array($response)) {
                        return $response;
                    }

                    $blocks[$i]['original_subline'] = $subline;
                    $blocks[$i]['subline'] = self::plain($response->text);
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

    /**
     * The templates print these fields as plain text, so markup from the model would show literally ("**Painting**").
     * Strip emphasis, headings and list markers, and fold every line break into a space.
     */
    private static function plain(string $text): string
    {
        $text = str_replace(['**', '__', '`'], '', $text);
        $text = (string) preg_replace('/^\s*(#{1,6}\s+|[-*•]\s+|\d+[.)]\s+)/mu', '', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
