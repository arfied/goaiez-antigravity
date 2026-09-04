<?php
declare(strict_types=1);
namespace App\Modules\X183\Domain;

final class X183Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
