<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

final class AgentClassifyAction
{
    public function handle(int $businessId, string $message): array
    {
        $lower = strtolower($message);
        $intent = 'general_inquiry';
        if (str_contains($lower, 'book') || str_contains($lower, 'appointment')) {
            $intent = 'booking_request';
        }

        return [
            'intent' => $intent,
            'confidence' => 0.95,
        ];
    }
}
