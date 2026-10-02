<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Models\Business;
use App\Modules\X103\Domain\BlockPatchSchema;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use App\Services\Visibility\CompetitorSiteNotes;
use Illuminate\Support\Str;

/**
 * "Make me a page": one owner request in plain words → one NEW unpublished
 * draft page. Same door as SiteEditProposeAction (whitelist + isValidBlock, every
 * block stamped source=ai), but the result is a Page row, not a pending_edit —
 * a draft nobody can see is the honest place for a proposal, and the owner's
 * verdict is Publish or Delete on the Pages list.
 */
final class SitePageProposeAction
{
    /** Real block types the page maker may NOT write: each needs a real price, person, photo, phone, address or link. */
    private const NEEDS_REAL_DETAILS = ['services', 'reviews_strip', 'gallery', 'team', 'video_embed', 'booking_button', 'booking_form', 'contact', 'form'];

    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly SiteBlockRenderer $renderer,
        private readonly CompetitorSiteNotes $peers,
        private readonly BusinessFacts $facts
    ) {}

    /**
     * @return array{status: string, reason?: string, page_id?: int, slug?: string, title?: string, blocks?: int, model?: string, left_out?: list<string>}
     */
    public function handle(int $businessId, string $request): array
    {
        if (trim($request) === '') {
            return ['status' => 'refused', 'reason' => 'empty_request'];
        }

        $reference = $this->peers->referenceBlock($businessId);
        $peerCount = $reference === '' ? 0 : count($this->peers->notesFor($businessId));

        // The ONLY facts the AI may state: the business's name and what the owner typed on the Facts screen.
        $labels = BusinessFactKey::forBusiness($businessId);
        $factLines = ['Business name: '.(string) Business::whereKey($businessId)->value('name')];
        foreach ($this->facts->all($businessId) as $key => $value) {
            $factLines[] = ($labels[$key]['label'] ?? $key).': '.$value;
        }
        $factsSection = "Facts the owner has stated (the ONLY facts you may use; state nothing else as fact):\n- ".implode("\n- ", $factLines);

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteAuthoring,
            prompt: "Owner request: {$request}\n\n{$factsSection}".($reference === '' ? '' : "\n\n".$reference),
            system: $this->registry->string('sites.page.system_prompt'),
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'blocks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => ['type' => ['type' => 'string']],
                            'required' => ['type'],
                            'additionalProperties' => true,
                        ],
                    ],
                    'explanation' => ['type' => 'string'],
                ],
                'required' => ['title', 'slug', 'blocks', 'explanation'],
            ]
        ));

        if (! $response->isUsable() || ! is_array($response->json)) {
            return [
                'status' => 'refused',
                'reason' => $response->failureReason ?? $response->refusalCategory ?? 'unknown',
                'model' => $response->model->value ?? 'unknown',
            ];
        }

        $title = trim((string) ($response->json['title'] ?? ''));
        if ($title === '') {
            return ['status' => 'refused', 'reason' => 'no_title'];
        }

        $whitelist = BlockPatchSchema::ADDABLE_TYPES;
        $validBlocks = [];
        $leftOut = [];
        foreach ($response->json['blocks'] ?? [] as $block) {
            $type = is_array($block) ? ($block['type'] ?? '') : '';
            if (is_array($block) && in_array($type, $whitelist, true) && $this->renderer->isValidBlock($block)) {
                $block['source'] = 'ai';
                $block['model'] = $response->model->value;
                if ($peerCount > 0) {
                    $block['peers'] = $peerCount;
                }
                $validBlocks[] = $block;
            } elseif (is_string($type) && in_array($type, self::NEEDS_REAL_DETAILS, true) && ! in_array($type, $leftOut, true)) {
                $leftOut[] = $type;
            }
        }
        if (count($validBlocks) === 0) {
            return ['status' => 'refused', 'reason' => 'no_valid_blocks'];
        }

        $baseSlug = Str::slug((string) ($response->json['slug'] ?? '')) ?: Str::slug($title) ?: 'page';
        $slug = $baseSlug;
        $counter = 2;
        while (Page::where('business_id', $businessId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $madeByAi = [
            'request' => $request,
            'explanation' => (string) ($response->json['explanation'] ?? ''),
            'model' => $response->model->value,
            'made_at' => now()->toIso8601String(),
        ];
        if ($peerCount > 0) {
            $madeByAi['peers'] = $peerCount;
        }

        $page = Page::create([
            'business_id' => $businessId,
            'slug' => $slug,
            'title' => $title,
            'is_tenant_edited' => false,
            'is_published' => false,
            'draft_blocks' => $validBlocks,
            'draft_meta' => [
                'made_by_ai' => $madeByAi,
            ],
        ]);

        return [
            'status' => 'made',
            'page_id' => (int) $page->id,
            'slug' => $slug,
            'title' => $title,
            'blocks' => count($validBlocks),
            'model' => $response->model->value,
            'left_out' => $leftOut,
        ];
    }
}
