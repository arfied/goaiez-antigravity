<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;

final class SiteEditProposeAction
{
    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly SiteBlockRenderer $renderer
    ) {}

    public function handle(int $businessId, int $pageId, string $request): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        if (trim($request) === '') {
            return [
                'status' => 'refused',
                'reason' => 'empty_request',
            ];
        }

        $systemPrompt = $this->registry->string('sites.edit.system_prompt');

        $prompt = "Owner request: {$request}\n\nCurrent blocks (JSON):\n".json_encode($page->draft_blocks ?? [], JSON_UNESCAPED_SLASHES);

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteCopy,
            prompt: $prompt,
            system: $systemPrompt,
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'blocks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => ['type' => 'string'],
                            ],
                            'required' => ['type'],
                            'additionalProperties' => true,
                        ],
                    ],
                    'explanation' => ['type' => 'string'],
                ],
                'required' => ['blocks', 'explanation'],
            ]
        ));

        if (! $response->isUsable() || ! is_array($response->json)) {
            return [
                'status' => 'refused',
                'reason' => $response->failureReason ?? $response->refusalCategory ?? 'unknown',
                'model' => $response->model->value ?? 'unknown',
            ];
        }

        $proposedBlocks = $response->json['blocks'] ?? [];
        $validBlocks = [];
        $whitelist = [
            'hero', 'about', 'services', 'reviews_strip',
            'booking_button', 'contact', 'faq', 'video_embed',
            'gallery', 'team', 'form',
        ];

        foreach ($proposedBlocks as $block) {
            $type = $block['type'] ?? '';
            if (in_array($type, $whitelist, true) && $this->renderer->isValidBlock($block)) {
                $block['source'] = 'ai';
                $block['model'] = $response->model->value;
                $validBlocks[] = $block;
            }
        }

        if (count($validBlocks) === 0) {
            return [
                'status' => 'refused',
                'reason' => 'no_valid_blocks',
            ];
        }

        $meta = $page->draft_meta ?? [];
        $meta['pending_edit'] = [
            'request' => $request,
            'blocks' => $validBlocks,
            'explanation' => (string) ($response->json['explanation'] ?? ''),
            'model' => $response->model->value,
            'drafted_at' => now()->toIso8601String(),
        ];
        $page->draft_meta = $meta;
        $page->save();

        return [
            'status' => 'proposed',
            'blocks' => count($validBlocks),
            'explanation' => $meta['pending_edit']['explanation'],
            'model' => $response->model->value,
            'cost_hundredths' => $response->costInHundredthsOfCents(),
        ];
    }
}
