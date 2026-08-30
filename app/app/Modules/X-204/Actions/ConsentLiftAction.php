<?php

declare(strict_types=1);

namespace App\Modules\X204\Actions;

use App\Modules\X204\Domain\ConsentService;

final class ConsentLiftAction
{
    public function __construct(private readonly ConsentService $service) {}

    public function handle(int $businessId, string $recipientPhone, string $channel = 'sms'): bool
    {
        return $this->service->lift($businessId, $recipientPhone, $channel);
    }
}
