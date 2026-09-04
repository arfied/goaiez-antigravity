<?php
declare(strict_types=1);

namespace App\Modules\X156\Domain;

final class IngestEngine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
