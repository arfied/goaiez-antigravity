<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X102\Actions\ChatQuestionReadAction;
use App\Modules\X103\Models\SiteAnsweredQuestion;
use App\Modules\X155\Actions\FormQuestionReadAction;
use App\Services\Config\DefaultsRegistry;

/**
 * The questions customers asked, from the site chat and the contact form, minus
 * the ones already answered on a page. Read through the other modules' actions
 * (the boundary seam), never their tables. Owner ruling 2026-09-24: chat and
 * forms are the sources; moderation gates publishing (wave 761).
 *
 * @return list<array{key: string, source: string, id: int, question: string, asked_at: string}>
 */
final class CustomerQuestionsAction
{
    public function __construct(
        private readonly ChatQuestionReadAction $chat,
        private readonly FormQuestionReadAction $forms,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function handle(int $businessId): array
    {
        $days = $this->registry->int('sites.questions.recent_days');
        $max = $this->registry->int('sites.questions.max');
        $answered = SiteAnsweredQuestion::query()->where('business_id', $businessId)
            ->get(['source_type', 'source_id'])
            ->map(fn ($r): string => $r->source_type.':'.$r->source_id)
            ->all();
        $rows = [];
        foreach ($this->chat->recent($businessId, $days, $max) as $q) {
            $rows[] = ['key' => 'chat:'.$q['id'], 'source' => 'chat', 'id' => $q['id'], 'question' => $q['question'], 'asked_at' => $q['asked_at']];
        }
        foreach ($this->forms->recent($businessId, $days, $max) as $q) {
            $rows[] = ['key' => 'form:'.$q['id'], 'source' => 'form', 'id' => $q['id'], 'question' => $q['question'], 'asked_at' => $q['asked_at']];
        }
        $rows = array_values(array_filter($rows, fn ($r): bool => ! in_array($r['key'], $answered, true)));
        usort($rows, fn ($a, $b) => strcmp($b['asked_at'], $a['asked_at']));

        return array_slice($rows, 0, $max);
    }
}
