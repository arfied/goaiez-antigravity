<?php

declare(strict_types=1);

namespace App\Modules\X110\Actions;

use App\Modules\X110\Domain\PixelEngine;

final class PixelVerifyAction
{
    public function __construct(private readonly PixelEngine $engine) {}

    public function handle(int $businessId, string $servedDomain, bool $thirdPartyCookiesDisabled = true): array
    {
        return $this->engine->verifyInstallation($businessId, $servedDomain, $thirdPartyCookiesDisabled);
    }
}
