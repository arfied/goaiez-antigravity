<?php

declare(strict_types=1);

namespace App\Modules\X155\Actions;

use App\Modules\X155\Models\FormSubmission;
use Illuminate\Support\Carbon;

/**
 * The free text a visitor typed into a form, for another module to read without
 * touching this module's tables. `payload['message']` when the form has that
 * field (both form builders name it so), else the longest string value of 15+
 * characters; spam is never returned. Names, phones and emails are not
 * questions and are skipped by the length rule in the common case — the
 * moderation step (wave 761) is what refuses personal data before anything is
 * published.
 *
 * @return list<array{id: int, question: string, asked_at: string}>
 */
final class FormQuestionReadAction
{
    public function recent(int $businessId, int $days, int $max): array
    {
        $rows = [];
        $subs = FormSubmission::query()
            ->where('business_id', $businessId)
            ->where('is_spam', false)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->orderByDesc('id')
            ->limit($max * 3)
            ->get(['id', 'payload', 'created_at']);
        foreach ($subs as $sub) {
            $payload = is_array($sub->payload) ? $sub->payload : [];
            $text = '';
            if (isset($payload['message']) && is_scalar($payload['message'])) {
                $text = (string) $payload['message'];
            } else {
                foreach ($payload as $key => $value) {
                    if (in_array((string) $key, ['name', 'first_name', 'last_name', 'phone', 'email'], true) || ! is_scalar($value)) {
                        continue;
                    }
                    $value = (string) $value;
                    if (mb_strlen($value) >= 15 && mb_strlen($value) > mb_strlen($text)) {
                        $text = $value;
                    }
                }
            }
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
            if ($text === '') {
                continue;
            }
            $rows[] = ['id' => (int) $sub->id, 'question' => mb_substr($text, 0, 500), 'asked_at' => $sub->created_at?->toIso8601String() ?? ''];
            if (count($rows) >= $max) {
                break;
            }
        }

        return $rows;
    }
}
