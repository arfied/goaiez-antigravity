<?php
declare(strict_types=1);
namespace App\Modules\X120\Domain;

final class X120Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
