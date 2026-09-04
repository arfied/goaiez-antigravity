<?php
declare(strict_types=1);
namespace App\Modules\X135\Domain;

final class X135Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
