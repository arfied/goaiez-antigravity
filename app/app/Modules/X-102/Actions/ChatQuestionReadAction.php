<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Models\ChatTurn;
use Illuminate\Support\Carbon;

/**
 * The questions visitors typed into the site chat, for another module to read
 * without touching this module's tables. A line counts as a question when it
 * ends with "?" or when the agent's reply to it carried a refusal_code (the
 * agent could not answer it). Greetings and captures are not questions.
 *
 * @return list<array{id: int, question: string, asked_at: string}>
 */
final class ChatQuestionReadAction
{
    public function recent(int $businessId, int $days, int $max): array
    {
        $since = Carbon::now()->subDays($days);
        $turns = ChatTurn::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since)
            ->orderBy('chat_session_id')
            ->orderBy('id')
            ->get(['id', 'chat_session_id', 'author_type', 'message', 'refusal_code', 'created_at']);

        $rows = [];
        $turns = $turns->values();
        foreach ($turns as $i => $turn) {
            if ($turn->author_type !== 'visitor') {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', (string) $turn->message) ?? '');
            if ($text === '') {
                continue;
            }
            $next = $turns[$i + 1] ?? null;
            $refused = $next !== null && $next->chat_session_id === $turn->chat_session_id && $next->author_type === 'agent' && $next->refusal_code !== null;
            if (! str_ends_with($text, '?') && ! $refused) {
                continue;
            }

            // Handle created_at formatting depending on if it's Carbon or string
            $createdAt = $turn->created_at;
            $askedAt = $createdAt instanceof Carbon ? $createdAt->toIso8601String() : (is_string($createdAt) ? Carbon::parse($createdAt)->toIso8601String() : '');

            $rows[] = ['id' => (int) $turn->id, 'question' => mb_substr($text, 0, 500), 'asked_at' => $askedAt];
        }
        usort($rows, fn ($a, $b) => strcmp($b['asked_at'], $a['asked_at']));

        return array_slice($rows, 0, $max);
    }
}
