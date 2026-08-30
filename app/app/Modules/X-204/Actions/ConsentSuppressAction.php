<?php

declare(strict_types=1);

namespace App\Modules\X204\Actions;

use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\Suppression;

final class ConsentSuppressAction
{
    public function __construct(private readonly ConsentService $service) {}

    public function handle(int $businessId, string $recipientPhone, string $channel = 'sms', string $reason = 'opt_out'): Suppression
    {
        return $this->service->suppress($businessId, $recipientPhone, $channel, $reason);
    }
}
