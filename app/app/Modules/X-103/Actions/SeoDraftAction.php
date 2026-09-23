<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;

final class SeoDraftAction
{
    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function handle(int $businessId, int $pageId): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        $maxTitleChars = $this->registry->int('sites.seo.title_max_chars');
        $maxDescChars = $this->registry->int('sites.seo.description_max_chars');

        $systemPrompt = $this->registry->string('sites.seo.system_prompt');
        $systemPrompt = str_replace(
            ['{title_max_chars}', '{description_max_chars}'],
            [(string) $maxTitleChars, (string) $maxDescChars],
            $systemPrompt
        );

        $blocksText = '';
        $blocks = $page->draft_blocks ?? [];
        foreach ($blocks as $block) {
            if (in_array($block['type'] ?? '', ['hero', 'about', 'services'])) {
                $blocksText .= ($block['text'] ?? '')."\n";
            }
        }

        $prompt = "Title: {$page->title}\nSlug: {$page->slug}\n\nContent:\n{$blocksText}";

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteCopy,
            prompt: $prompt,
            system: $systemPrompt,
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                ],
                'required' => ['title', 'description'],
                'additionalProperties' => false,
            ]
        ));

        if ($response->refused || $response->failureReason !== null) {
            return [
                'status' => 'refused',
                'reason' => $response->refused ? 'model_refused' : $response->failureReason,
            ];
        }

        $json = $response->json;
        if (! $json) {
            return [
                'status' => 'refused',
                'reason' => 'no_json_produced',
            ];
        }

        $title = $this->truncateToLimit($json['title'] ?? '', $maxTitleChars);
        $description = $this->truncateToLimit($json['description'] ?? '', $maxDescChars);

        $page->seo_title = $title;
        $page->seo_description = $description;
        $page->save();

        return [
            'status' => 'drafted',
            'title' => $title,
            'description' => $description,
            'model' => $response->model->value,
            'cost_hundredths' => $response->costInHundredthsOfCents(),
        ];
    }

    private function truncateToLimit(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $text = mb_substr($text, 0, $limit + 1);
        $lastSpace = mb_strrpos($text, ' ');

        if ($lastSpace !== false) {
            return rtrim(mb_substr($text, 0, $lastSpace));
        }

        return rtrim(mb_substr($text, 0, $limit));
    }
}
