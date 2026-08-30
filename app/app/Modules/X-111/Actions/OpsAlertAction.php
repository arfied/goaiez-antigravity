<?php

declare(strict_types=1);

namespace App\Modules\X111\Actions;

use App\Modules\X111\Domain\OpsEngine;
use App\Modules\X111\Models\OperatorAlert;

final class OpsAlertAction
{
    public function __construct(private readonly OpsEngine $engine = new OpsEngine) {}

    public function handle(int $businessId, string $severity, string $message): OperatorAlert
    {
        return $this->engine->createAlert($businessId, $severity, $message);
    }
}
