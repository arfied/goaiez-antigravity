<?php

declare(strict_types=1);

namespace App\Modules\X211\Console;

use Illuminate\Console\Command;

final class RuntimeProofCommand extends Command
{
    protected $signature = 'x211:runtime-proof';

    protected $description = 'Generate runtime proof for X-211';

    public function handle(): int
    {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $this->error('X-211 has no runtime proof: a recovery cannot reach a gateway charge id, because payments carries no plan column (see ruling 102). No artifact was written.');

        return self::FAILURE;
    }
}
