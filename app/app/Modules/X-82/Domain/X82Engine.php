<?php

declare(strict_types=1);

namespace App\Modules\X82\Domain;

final class X82Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException('X-82 REFUSES');
    }
}
