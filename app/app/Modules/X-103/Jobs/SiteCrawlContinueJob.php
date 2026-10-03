<?php

declare(strict_types=1);

namespace App\Modules\X103\Jobs;

use App\Modules\X103\Actions\SiteCrawlAction;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Reads the pages a crawl had to leave queued because the shared website-reading budget (tenant_site: four reads a minute
 * for the whole platform) was used up (the owner's build, 2026-10-03: "pages 3, refused 13"). Each round reads what the
 * budget allows and, if pages remain, queues the next round a minute later — at most SiteCrawlAction::MAX_ROUNDS rounds, so
 * a budget kept busy by other businesses cannot keep this alive forever.
 */
final class SiteCrawlContinueJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $businessId,
        public readonly int $locationId,
        public readonly int $round = 1,
    ) {}

    public function handle(SiteCrawlAction $action): void
    {
        Tenancy::actingAs($this->businessId, function () use ($action): void {
            $action->continue($this->businessId, $this->locationId, $this->round);
        });
    }
}
