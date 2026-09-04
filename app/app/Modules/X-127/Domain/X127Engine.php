<?php
declare(strict_types=1);
namespace App\Modules\X127\Domain;

final class X127Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
