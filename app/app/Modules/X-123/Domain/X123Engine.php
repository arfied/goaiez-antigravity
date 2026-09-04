<?php
declare(strict_types=1);
namespace App\Modules\X123\Domain;

final class X123Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
