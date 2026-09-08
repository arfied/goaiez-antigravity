<?php

declare(strict_types=1);

namespace App\Modules\X117\Console;

use Illuminate\Console\Command;

final class RuntimeProofCommand extends Command
{
    protected $signature = 'x117:runtime-proof';

    protected $description = 'Generate runtime proof for X-117';

    public function handle(): int
    {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $this->error(
            "X-117 has no runtime proof to write: after the checkout listener stopped calling\n".
            "the gateway (ruling 45) nothing in this flow reaches a payment provider, so there\n".
            "is no vendor-issued artifact id to capture. Waiting on a browser-side Stripe\n".
            "Elements / publishable-key card-entry surface (X-120 CardVault's, parked behind a\n".
            'contract by ruling 20).'
        );

        return self::FAILURE;
    }
}
