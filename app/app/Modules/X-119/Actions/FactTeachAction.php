<?php

declare(strict_types=1);

namespace App\Modules\X119\Actions;

use App\Modules\X119\Domain\FactResolver;

final class FactTeachAction
{
    public function __construct(private readonly FactResolver $resolver) {}

    public function handle(int $businessId, string $key, string $value, string $source = 'volunteered'): array
    {
        return $this->resolver->teach($businessId, $key, $value, $source);
    }
}
