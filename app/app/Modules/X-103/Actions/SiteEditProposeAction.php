<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Assistant\PriceBook;
use App\Services\Config\DefaultsRegistry;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Industry\SiteStyle;
use App\Support\PlanPricing;

final class SiteEditProposeAction
{
    public function __construct(
        private readonly PriceBook $priceBook,
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly SiteBlockRenderer $renderer,
        private readonly IndustryStartingPoints $startingPoints
    ) {}

    public function handle(int $businessId, int $pageId, string $request, bool $continue = false): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        if (trim($request) === '') {
            return [
                'status' => 'refused',
                'reason' => 'empty_request',
            ];
        }

        $systemPrompt = $this->registry->string('sites.edit.system_prompt');

        $currentBlocks = $page->draft_blocks ?? [];
        if ($continue && isset($page->draft_meta['pending_edit'])) {
            $currentBlocks = $page->draft_meta['pending_edit']['blocks'] ?? [];
        }

        $facts = [];
        $priceList = $this->priceBook->list();
        foreach ($priceList->entries as $entry) {
            if ($entry->isConfirmed()) {
                $priceText = PlanPricing::format($entry->amount());
                if ($entry->isRange()) {
                    $priceText .= ' - '.PlanPricing::format($entry->upperAmount());
                }
                $facts[] = "Service: {$entry->label} (Price: {$priceText})";
            }
        }

        if (empty($facts)) {
            $pricesSection = 'Prices you may use: none — do not state any price.';
        } else {
            $pricesSection = "Prices you may use (never any other price):\n".implode("\n", $facts);
        }

        $baseStyle = $this->startingPoints->forBusiness($businessId);
        $currentStyleJson = json_encode([
            'palette' => $baseStyle['palette'] ?? [],
            'type_pairing' => $baseStyle['type_pairing'] ?? [],
        ], JSON_UNESCAPED_SLASHES);
        $fontStacks = implode(' | ', SiteStyle::FONT_STACKS);

        $prompt = "Owner request: {$request}\n\n{$pricesSection}\n\nCurrent blocks (JSON):\n".json_encode($currentBlocks, JSON_UNESCAPED_SLASHES);
        $maxImages = $this->registry->int('sites.edit.max_images');
        $prompt .= "\n\nCurrent style (JSON): {$currentStyleJson}\nIf the request is about colours, fonts or mood, you may also return \"style\": {\"palette\": {…}, \"type_pairing\": {\"heading\": …, \"body\": …}} using hex colours and only these fonts: {$fontStacks}. Otherwise omit \"style\".";
        $prompt .= "\n\nIf the owner asks for a picture, you may also return \"images\": [{\"block_index\": <index in your blocks>, \"description\": \"<what the picture shows>\"}], at most {$maxImages} items, only for hero or gallery blocks.";

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
                    'style' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                    'images' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'block_index' => ['type' => 'integer'],
                                'description' => ['type' => 'string'],
                            ],
                            'required' => ['block_index', 'description'],
                        ],
                    ],
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
            'gallery', 'team', 'form', 'booking_form',
        ];

        foreach ($proposedBlocks as $block) {
            $type = $block['type'] ?? '';
            if (in_array($type, $whitelist, true) && $this->renderer->isValidBlock($block)) {
                $block['source'] = 'ai';
                $block['model'] = $response->model->value;
                $validBlocks[] = $block;
            }
        }

        $proposedStyle = $response->json['style'] ?? null;
        $validStyle = null;
        $styleRefusedReason = null;

        if (is_array($proposedStyle)) {
            $validation = SiteStyle::validate($proposedStyle, $baseStyle);
            if ($validation['ok']) {
                $validStyle = $validation['style'];
            } else {
                $styleRefusedReason = $validation['reason'];
            }
        }

        if (count($validBlocks) === 0 && $validStyle === null) {
            return [
                'status' => 'refused',
                'reason' => $styleRefusedReason ?? 'no_valid_blocks',
            ];
        }

        $requestedImages = $response->json['images'] ?? [];
        $imagesGenerated = 0;
        $imageNotes = [];

        if (! empty($requestedImages)) {
            $imageAction = app(SiteImageGenerateAction::class);
            $maxImages = $this->registry->int('sites.edit.max_images');
            $requestedImages = array_slice($requestedImages, 0, $maxImages);

            foreach ($requestedImages as $imgReq) {
                $idx = $imgReq['block_index'] ?? null;
                $desc = $imgReq['description'] ?? null;

                if ($idx === null || ! is_int($idx) || ! isset($validBlocks[$idx]) || empty($desc)) {
                    continue;
                }

                $blockType = $validBlocks[$idx]['type'] ?? '';
                if ($blockType !== 'hero' && $blockType !== 'gallery') {
                    continue;
                }

                $res = $imageAction->handle($businessId, $desc);
                if ($res['status'] === 'generated') {
                    if ($blockType === 'hero') {
                        $validBlocks[$idx]['image_path'] = $res['path'];
                    } else {
                        if (! isset($validBlocks[$idx]['items'])) {
                            $validBlocks[$idx]['items'] = [];
                        }
                        $validBlocks[$idx]['items'][] = ['image_path' => $res['path']];
                    }
                    $imagesGenerated++;
                } else {
                    $imageNotes[] = "Could not generate picture '{$desc}': ".($res['reason'] ?? 'unknown');
                }
            }
        }

        $meta = $page->draft_meta ?? [];
        $thread = [];

        if ($continue && isset($meta['pending_edit'])) {
            $thread = $meta['pending_edit']['thread'] ?? [];
            if (empty($thread)) {
                $thread[] = [
                    'request' => $meta['pending_edit']['request'] ?? '',
                    'explanation' => $meta['pending_edit']['explanation'] ?? '',
                    'at' => $meta['pending_edit']['drafted_at'] ?? '',
                ];
            }
        }

        $explanation = (string) ($response->json['explanation'] ?? '');
        $now = now()->toIso8601String();

        $thread[] = [
            'request' => $request,
            'explanation' => $explanation,
            'at' => $now,
        ];

        $pendingEdit = [
            'request' => $request,
            'blocks' => count($validBlocks) > 0 ? $validBlocks : $currentBlocks,
            'explanation' => $explanation,
            'model' => $response->model->value,
            'drafted_at' => $now,
            'thread' => $thread,
        ];

        if (! empty($imageNotes)) {
            $pendingEdit['image_notes'] = implode("\n", $imageNotes);
        }

        if ($validStyle !== null) {
            $pendingEdit['style'] = $validStyle;
        }
        if ($styleRefusedReason !== null) {
            $pendingEdit['style_refused'] = $styleRefusedReason;
        }

        $meta['pending_edit'] = $pendingEdit;
        $page->draft_meta = $meta;
        $page->save();

        return [
            'status' => 'proposed',
            'blocks' => count($validBlocks) > 0 ? count($validBlocks) : count($currentBlocks),
            'explanation' => $meta['pending_edit']['explanation'],
            'model' => $response->model->value,
            'cost_hundredths' => $response->costInHundredthsOfCents(),
            'images' => $imagesGenerated,
        ];
    }
}
