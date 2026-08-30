<?php

declare(strict_types=1);

namespace App\Modules\X161\Domain;

final class SandboxCarrierTransport
{
    public function deliver(string $recipient, string $body): bool
    {
        // Sandbox carrier transport intercepts all sends, strictly avoiding live carrier APIs (TEST ANCHOR & G11-33)
        return true;
    }
}
