<?php
declare(strict_types=1);
namespace App\Modules\X144\Domain;

final class X144Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
