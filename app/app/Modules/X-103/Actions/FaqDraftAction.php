<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Models\Review;
use App\Modules\X103\Models\Page;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Assistant\PriceBook;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlanPricing;
use Illuminate\Support\Carbon;

final class FaqDraftAction
{
    public function __construct(
        private readonly PriceBook $priceBook,
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry
    ) {}

    public function handle(int $businessId, int $pageId): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        $maxSources = $this->registry->int('sites.faq.max_sources');
        $reviewsMax = $this->registry->int('sites.draft.reviews_max');
        $maxItems = $this->registry->int('sites.faq.max_items');
        $systemPrompt = $this->registry->string('sites.faq.system_prompt');

        $facts = [];

        $priceList = $this->priceBook->list();
        $sourceCount = 0;
        foreach ($priceList->entries as $entry) {
            if ($entry->isConfirmed()) {
                $priceText = PlanPricing::format($entry->amount());
                if ($entry->isRange()) {
                    $priceText .= ' - '.PlanPricing::format($entry->upperAmount());
                }
                $facts[] = "Service: {$entry->label} (Price: {$priceText})";
                $sourceCount++;
                if ($sourceCount >= $maxSources) {
                    break;
                }
            }
        }

        // displayable() is the moderation gate — the same one the public widget feed applies; display fails closed.
        $reviews = Review::query()->displayable()->where('business_id', $businessId)
            ->where('display_on_website', true)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->orderByDesc('rating')
            ->take($reviewsMax)
            ->get();

        foreach ($reviews as $r) {
            $facts[] = "Review: {$r->comment}";
        }

        if (count($facts) === 0) {
            return [
                'status' => 'refused',
                'reason' => 'no_facts_available',
            ];
        }

        $prompt = implode("\n", $facts);

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteCopy,
            prompt: $prompt,
            system: $systemPrompt,
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'question' => ['type' => 'string'],
                                'answer' => ['type' => 'string'],
                            ],
                            'required' => ['question', 'answer'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ]
        ));

        if (! $response->isUsable() || ! is_array($response->json)) {
            return [
                'status' => 'refused',
                'reason' => $response->failureReason ?? $response->refusalCategory ?? 'unknown',
            ];
        }

        $items = $response->json['items'] ?? [];
        if (count($items) > $maxItems) {
            $items = array_slice($items, 0, $maxItems);
        }

        $meta = $page->draft_meta ?? [];
        $meta['pending_faq'] = [
            'items' => $items,
            'model' => $response->model->value,
            'drafted_at' => Carbon::now()->toIso8601String(),
        ];
        $page->draft_meta = $meta;
        $page->save();

        return [
            'status' => 'drafted',
            'items' => count($items),
            'model' => $response->model->value,
            'cost_hundredths' => $response->costInHundredthsOfCents(),
        ];
    }
}
