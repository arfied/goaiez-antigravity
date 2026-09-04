<?php
declare(strict_types=1);
namespace App\Modules\X114\Domain;

final class X114Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
