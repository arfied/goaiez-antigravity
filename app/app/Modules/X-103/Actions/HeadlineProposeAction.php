<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Models\Review;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Assistant\PriceBook;
use App\Services\Config\DefaultsRegistry;
use App\Services\Visibility\CompetitorSiteNotes;
use App\Support\PlanPricing;

final class HeadlineProposeAction
{
    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly PageReadAction $pages,
        private readonly CompetitorSiteNotes $peers,
        private readonly PriceBook $priceBook
    ) {}

    public function handle(int $businessId): array
    {
        $home = $this->pages->homeFor($businessId);
        $heroBlock = null;
        if ($home && is_array($home->draft_blocks)) {
            foreach ($home->draft_blocks as $block) {
                if (($block['type'] ?? '') === 'hero') {
                    $heroBlock = $block;
                    break;
                }
            }
        }

        if (! $heroBlock || ! isset($heroBlock['headline']) || ! isset($heroBlock['subline'])) {
            return ['status' => 'refused', 'reason' => 'no_hero'];
        }

        $headline = $heroBlock['headline'];
        $subline = $heroBlock['subline'];

        $maxSources = $this->registry->int('sites.faq.max_sources');
        $reviewsMax = $this->registry->int('sites.draft.reviews_max');

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

        $topicsData = $this->peers->topicsFor($businessId);
        $topicsString = empty($topicsData['topics']) ? 'None' : implode(', ', $topicsData['topics']);

        $factsString = implode("\n", $facts);

        $prompt = "Current headline: {$headline}\nSubline: {$subline}\n\nFacts:\n{$factsString}\n\nTopics nearby sites cover: {$topicsString}";
        $systemPrompt = $this->registry->string('sites.variant.system_prompt');

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteCopy,
            prompt: $prompt,
            system: $systemPrompt,
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'headlines' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['headlines'],
            ]
        ));

        if (! $response->isUsable()) {
            return ['status' => 'refused', 'reason' => $response->failureReason ?? $response->refusalCategory ?? 'unknown'];
        }

        $rawHeadlines = $response->json['headlines'] ?? [];
        $validHeadlines = [];
        foreach ($rawHeadlines as $h) {
            $trimmed = trim((string) $h);
            if ($trimmed !== '' && $trimmed !== $headline && mb_strlen($trimmed) <= 120) {
                $validHeadlines[] = $trimmed;
            }
        }

        if (count($validHeadlines) === 0) {
            return ['status' => 'refused', 'reason' => 'no_valid_headlines'];
        }

        return [
            'status' => 'proposed',
            'headlines' => array_slice($validHeadlines, 0, 2),
            'model' => $response->model->value,
        ];
    }
}
