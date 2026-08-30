<?php

declare(strict_types=1);

namespace App\Modules\X111\Actions;

use App\Modules\X111\Domain\OpsEngine;
use App\Modules\X111\Models\TenantTicket;

final class OpsTicketAction
{
    public function __construct(private readonly OpsEngine $engine = new OpsEngine) {}

    public function handle(int $businessId, string $fullTranscript, string $category = 'general'): TenantTicket
    {
        return $this->engine->createHumanTicket($businessId, $fullTranscript, $category);
    }
}
