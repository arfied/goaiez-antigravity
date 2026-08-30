<?php

declare(strict_types=1);

namespace App\Modules\X151\Actions;

use App\Modules\X151\Domain\FetchEngine;

final class FetchRunAction
{
    public function __construct(private readonly FetchEngine $engine = new FetchEngine) {}

    public function handle(
        int $businessId,
        string $domain,
        string $url,
        bool $encounterCaptcha = false,
        int $currentActiveWorkers = 0
    ): array {
        return $this->engine->fetch($businessId, $domain, $url, $encounterCaptcha, $currentActiveWorkers);
    }
}
