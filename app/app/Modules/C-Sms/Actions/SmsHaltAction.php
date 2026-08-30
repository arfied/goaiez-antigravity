<?php

declare(strict_types=1);

namespace App\Modules\CSms\Actions;

use App\Modules\CSms\Domain\SmsComposer;

final class SmsHaltAction
{
    public function __construct(private readonly SmsComposer $composer) {}

    public function handle(int $businessId, int $compositionId): bool
    {
        return $this->composer->halt($businessId, $compositionId);
    }
}
