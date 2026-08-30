<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\SiteFork;

final class SiteBuildAction
{
    public function __construct(private readonly SiteEngine $engine) {}

    public function handle(int $businessId, string $templateId): SiteFork
    {
        return $this->engine->forkSite($businessId, $templateId);
    }
}
