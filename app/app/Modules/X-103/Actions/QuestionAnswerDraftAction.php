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
use App\Services\Content\ContentModerator;
use App\Services\Content\PageCopy;
use App\Enums\ModerationFlag;
use App\Support\PlanPricing;
use Illuminate\Support\Carbon;

final class QuestionAnswerDraftAction
{
    public function __construct(
        private readonly PriceBook $priceBook,
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly ContentModerator $moderator
    ) {}

    public function handle(int $businessId, int $pageId, string $source, int $sourceId, string $question): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        if (isset($page->draft_meta['pending_faq'])) {
            return [
                'status' => 'refused',
                'reason' => 'pending_faq_exists',
            ];
        }

        $verdict = $this->moderator->moderate(new PageCopy(trim($question), null, ''));

        if (! $verdict->wasModerated()) {
            return [
                'status' => 'refused',
                'reason' => 'moderation_unavailable',
                'detail' => (string) ($verdict->reason ?? 'unknown'),
            ];
        }

        if (in_array(ModerationFlag::Refused, $verdict->flags ?? [], true)) {
            return [
                'status' => 'refused',
                'reason' => 'moderation_refused',
            ];
        }

        if ($verdict->isFlagged()) {
            return [
                'status' => 'refused',
                'reason' => 'flagged',
                'flags' => $verdict->flagValues(),
            ];
        }

        $facts = $this->facts($businessId);

        if (count($facts) === 0) {
            return [
                'status' => 'refused',
                'reason' => 'no_facts_available',
            ];
        }

        $systemPrompt = $this->registry->string('sites.questions.answer_system_prompt');
        $prompt = "Customer question: {$question}\n\nFacts:\n".implode("\n", $facts);

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteCopy,
            prompt: $prompt,
            system: $systemPrompt,
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'question' => ['type' => 'string'],
                    'answer' => ['type' => 'string'],
                ],
                'required' => ['question', 'answer'],
            ]
        ));

        if (! $response->isUsable() || ! is_array($response->json)) {
            return [
                'status' => 'refused',
                'reason' => $response->failureReason ?? $response->refusalCategory ?? 'unknown',
            ];
        }

        $meta = $page->draft_meta ?? [];
        $meta['pending_faq'] = [
            'items' => [
                [
                    'question' => $response->json['question'],
                    'answer' => $response->json['answer'],
                ]
            ],
            'model' => $response->model->value,
            'drafted_at' => Carbon::now()->toIso8601String(),
            'source' => [
                'type' => $source,
                'id' => $sourceId,
                'question' => mb_substr($question, 0, 500),
            ],
        ];
        $page->draft_meta = $meta;
        $page->save();

        return [
            'status' => 'drafted',
            'model' => $response->model->value,
            'cost_hundredths' => $response->costInHundredthsOfCents(),
        ];
    }

    private function facts(int $businessId): array
    {
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

        return $facts;
    }
}
