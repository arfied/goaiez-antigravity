<?php
declare(strict_types=1);
namespace App\Modules\X102\Domain;

final class X102Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
