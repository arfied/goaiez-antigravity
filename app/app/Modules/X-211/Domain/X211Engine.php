<?php
declare(strict_types=1);
namespace App\Modules\X211\Domain;

final class X211Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
