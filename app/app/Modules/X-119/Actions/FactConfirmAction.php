<?php

declare(strict_types=1);

namespace App\Modules\X119\Actions;

use App\Modules\X119\Domain\FactResolver;

final class FactConfirmAction
{
    public function __construct(private readonly FactResolver $resolver) {}

    public function handle(int $businessId, int $factId): array
    {
        return $this->resolver->confirm($businessId, $factId);
    }
}
