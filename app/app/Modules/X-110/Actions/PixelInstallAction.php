<?php

declare(strict_types=1);

namespace App\Modules\X110\Actions;

use App\Modules\X110\Domain\PixelEngine;

final class PixelInstallAction
{
    public function __construct(private readonly PixelEngine $engine) {}

    public function handle(int $businessId, string $tenantDomain): array
    {
        $verification = $this->engine->verifyInstallation($businessId, $tenantDomain);

        return array_merge($verification, [
            'tenant_domain' => $tenantDomain,
            'script_tag' => "<script async src=\"https://analytics.{$tenantDomain}/tag.js\"></script>",
            'status' => 'ready',
        ]);
    }
}
