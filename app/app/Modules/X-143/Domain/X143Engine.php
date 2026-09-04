<?php
declare(strict_types=1);
namespace App\Modules\X143\Domain;

final class X143Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
