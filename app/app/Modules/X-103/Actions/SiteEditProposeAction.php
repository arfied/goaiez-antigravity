<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Domain\BlockPatchSchema;
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

        $numberedBlocks = [];
        foreach ($currentBlocks as $i => $block) {
            $numberedBlocks[$i] = array_merge(['block_index' => $i], $block);
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

        $prompt = "Owner request: {$request}\n\n{$pricesSection}\n\nCurrent blocks (JSON):\n".json_encode($numberedBlocks, JSON_UNESCAPED_SLASHES);
        $maxImages = $this->registry->int('sites.edit.max_images');
        $prompt .= "\n\nCurrent style (JSON): {$currentStyleJson}\nIf the request is about colours, fonts or mood, you may also return \"style\": {\"palette\": {…}, \"type_pairing\": {\"heading\": …, \"body\": …}} using hex colours and only these fonts: {$fontStacks}. Otherwise omit \"style\".";
        $prompt .= "\n\nIf the owner asks for a picture, you may also return \"images\": [{\"block_index\": <index in your blocks>, \"description\": \"<what the picture shows>\"}], at most {$maxImages} items, only for hero or gallery blocks.";

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteAuthoring,
            prompt: $prompt,
            system: $systemPrompt,
            jsonSchema: BlockPatchSchema::schema()
        ));

        if (! $response->isUsable() || ! is_array($response->json)) {
            return [
                'status' => 'refused',
                'reason' => $response->failureReason ?? $response->refusalCategory ?? 'unknown',
                'model' => $response->model->value ?? 'unknown',
            ];
        }

        $proposedStyle = $response->json['style'] ?? null;
        $validStyle = null;
        $styleRefusedReason = null;

        if (is_array($proposedStyle) && ! empty($proposedStyle)) {
            $validation = SiteStyle::validate($proposedStyle, $baseStyle);
            if ($validation['ok']) {
                $validStyle = $validation['style'];
            } else {
                $styleRefusedReason = $validation['reason'];
            }
        }

        $patches = $response->json['patches'] ?? [];
        $requestedImages = $response->json['images'] ?? [];

        if (count($patches) === 0 && count($requestedImages) === 0 && $validStyle === null) {
            return [
                'status' => 'refused',
                'reason' => $styleRefusedReason ?? 'no_valid_blocks',
                'explanation' => (string) ($response->json['explanation'] ?? ''),
            ];
        }

        $imagesGenerated = 0;
        $imageNotes = [];

        if (! empty($requestedImages)) {
            $imageAction = app(SiteImageGenerateAction::class);
            $requestedImages = array_slice($requestedImages, 0, $maxImages);

            foreach ($requestedImages as $imgReq) {
                $idx = $imgReq['block_index'] ?? null;
                $desc = $imgReq['description'] ?? null;

                if ($idx === null || ! is_int($idx) || ! isset($currentBlocks[$idx]) || empty($desc)) {
                    continue;
                }

                $blockType = $currentBlocks[$idx]['type'] ?? '';
                if ($blockType !== 'hero' && $blockType !== 'gallery') {
                    continue;
                }

                $res = $imageAction->handle($businessId, $desc);
                if ($res['status'] === 'generated') {
                    if ($blockType === 'hero') {
                        $patches[] = [
                            'op' => 'set_string',
                            'block_index' => $idx,
                            'field' => 'image_path',
                            'value' => $res['path'],
                        ];
                    } else {
                        $currentItems = $currentBlocks[$idx]['items'] ?? [];
                        foreach ($patches as $p) {
                            if ($p['block_index'] === $idx && $p['field'] === 'items' && $p['op'] === 'set_image_list') {
                                $currentItems = $p['images'];
                            }
                        }
                        $currentItems[] = ['image_path' => $res['path']];

                        $patches[] = [
                            'op' => 'set_image_list',
                            'block_index' => $idx,
                            'field' => 'items',
                            'images' => $currentItems,
                        ];
                    }
                    $imagesGenerated++;
                } else {
                    $imageNotes[] = "Could not generate picture '{$desc}': ".($res['reason'] ?? 'unknown');
                }
            }
        }

        $explanation = (string) ($response->json['explanation'] ?? '');

        return [
            'status' => 'proposed',
            'patches' => $patches,
            'explanation' => $explanation,
            'model' => $response->model->value,
            'cost_hundredths' => $response->costInHundredthsOfCents(),
            'images' => $imagesGenerated,
            'image_notes' => implode("\n", $imageNotes),
            'style' => $validStyle,
            'style_refused' => $styleRefusedReason,
        ];
    }
}
