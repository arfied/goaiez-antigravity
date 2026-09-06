<?php

declare(strict_types=1);

namespace App\Modulesf\Domain;

final class X66Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException('X-66 REFUSES');
    }
}
