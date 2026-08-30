<?php

declare(strict_types=1);

namespace App\Modules\X119\Actions;

use App\Modules\X119\Domain\FactResolver;

final class FactLookupAction
{
    public function __construct(private readonly FactResolver $resolver) {}

    public function handle(int $businessId, string $key, string $channel = 'customer'): array
    {
        return $this->resolver->lookup($businessId, $key, $channel);
    }
}
