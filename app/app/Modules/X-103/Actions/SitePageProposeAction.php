<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;
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
    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly SiteBlockRenderer $renderer,
        private readonly CompetitorSiteNotes $peers
    ) {}

    /**
     * @return array{status: string, reason?: string, page_id?: int, slug?: string, title?: string, blocks?: int, model?: string}
     */
    public function handle(int $businessId, string $request): array
    {
        if (trim($request) === '') {
            return ['status' => 'refused', 'reason' => 'empty_request'];
        }

        $reference = $this->peers->referenceBlock($businessId);
        $peerCount = $reference === '' ? 0 : count($this->peers->notesFor($businessId));

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteAuthoring,
            prompt: "Owner request: {$request}".($reference === '' ? '' : "\n\n".$reference),
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

        $whitelist = ['hero', 'about', 'services', 'faq', 'booking_button', 'contact'];
        $validBlocks = [];
        foreach ($response->json['blocks'] ?? [] as $block) {
            $type = $block['type'] ?? '';
            if (is_array($block) && in_array($type, $whitelist, true) && $this->renderer->isValidBlock($block)) {
                $block['source'] = 'ai';
                $block['model'] = $response->model->value;
                if ($peerCount > 0) {
                    $block['peers'] = $peerCount;
                }
                $validBlocks[] = $block;
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
        ];
    }
}
