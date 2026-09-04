<?php
declare(strict_types=1);
namespace App\Modules\X180\Domain;

final class X180Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
