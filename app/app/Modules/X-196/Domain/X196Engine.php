<?php
declare(strict_types=1);
namespace App\Modules\X196\Domain;

final class X196Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
