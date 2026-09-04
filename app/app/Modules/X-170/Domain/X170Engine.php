<?php
declare(strict_types=1);
namespace App\Modules\X170\Domain;

final class X170Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
