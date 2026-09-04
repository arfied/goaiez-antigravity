<?php

declare(strict_types=1);

namespace App\Modules\X203\Domain;

final class X203Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException('REFUSES: Domain constraints enforced.');
    }
}
