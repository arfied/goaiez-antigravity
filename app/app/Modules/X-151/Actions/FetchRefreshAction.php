<?php

declare(strict_types=1);

namespace App\Modules\X151\Actions;

use App\Modules\X151\Domain\FetchEngine;
use App\Modules\X151\Models\Fetch;

final class FetchRefreshAction
{
    public function __construct(private readonly FetchEngine $engine = new FetchEngine) {}

    public function handle(int $businessId, int $fetchId): Fetch
    {
        return $this->engine->markStale($businessId, $fetchId);
    }
}
