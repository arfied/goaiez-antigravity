<?php
declare(strict_types=1);
namespace App\Modules\X184\Domain;

final class X184Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
