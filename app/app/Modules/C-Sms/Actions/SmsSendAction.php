<?php

declare(strict_types=1);

namespace App\Modules\CSms\Actions;

use App\Modules\CSms\Domain\SmsComposer;

final class SmsSendAction
{
    public function __construct(private readonly SmsComposer $composer) {}

    public function handle(
        int $businessId,
        string $recipientPhone,
        string $body,
        string $messageClass = 'transactional',
        string $recipientLocalTime = '12:00'
    ): array {
        return $this->composer->send($businessId, $recipientPhone, $body, $messageClass, $recipientLocalTime);
    }
}
