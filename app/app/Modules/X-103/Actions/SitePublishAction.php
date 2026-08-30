<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\SiteEngine;

final class SitePublishAction
{
    public function __construct(private readonly SiteEngine $engine) {}

    public function handle(int $businessId, int $pageId, array $contentBlocks): array
    {
        return $this->engine->publish($businessId, $pageId, $contentBlocks);
    }
}
