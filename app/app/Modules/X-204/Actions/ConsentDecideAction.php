<?php

declare(strict_types=1);

namespace App\Modules\X204\Actions;

use App\Modules\X204\Domain\ConsentService;

final class ConsentDecideAction
{
    public function __construct(private readonly ConsentService $service) {}

    public function handle(int $businessId, string $recipientPhone, string $channel = 'sms', string $state = 'opted_in'): array
    {
        return $this->service->decide($businessId, $recipientPhone, $channel, $state);
    }
}
