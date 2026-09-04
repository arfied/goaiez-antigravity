<?php
declare(strict_types=1);
namespace App\Modules\X111\Domain;

final class X111Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
