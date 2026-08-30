<?php

declare(strict_types=1);

namespace App\Modules\CSms\Actions;

use App\Modules\CSms\Domain\SmsComposer;

final class SmsComposeAction
{
    public function __construct(private readonly SmsComposer $composer) {}

    public function handle(string $body): array
    {
        return $this->composer->calculateSegments($body);
    }
}
