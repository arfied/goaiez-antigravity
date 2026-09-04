<?php
declare(strict_types=1);

namespace App\Modules\X218\Domain;

final class X218Engine
{
    public function enforceCapabilities(): void {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
