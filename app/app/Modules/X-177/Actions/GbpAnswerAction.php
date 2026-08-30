<?php

declare(strict_types=1);

namespace App\Modules\X177\Actions;

use App\Modules\X177\Events\GbpQuestionAnswered;
use App\Modules\X177\Models\GbpConnection;
use Illuminate\Support\Facades\Event;

final class GbpAnswerAction
{
    public function answer(int $businessId, int $connectionId, string $questionId, string $answerText): array
    {
        $conn = GbpConnection::where('business_id', $businessId)->findOrFail($connectionId);

        Event::dispatch(new GbpQuestionAnswered($businessId, $conn->id, $questionId));

        return [
            'status' => 'answered',
            'connection_id' => $conn->id,
            'question_id' => $questionId,
            'answer' => $answerText,
        ];
    }
}
