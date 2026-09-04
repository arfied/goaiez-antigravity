<?php
declare(strict_types=1);

namespace App\Modules\X150\Domain;

use InvalidArgumentException;

final class ProviderEngine
{
    public function enforceN150Capabilities(bool $hasCapabilities): void
    {
        if (!$hasCapabilities) {
            throw new InvalidArgumentException('Missing N-150 capabilities');
        }
    }
}
