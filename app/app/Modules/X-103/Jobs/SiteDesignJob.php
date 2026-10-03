<?php

declare(strict_types=1);

namespace App\Modules\X103\Jobs;

use App\Modules\X103\Actions\SiteDesignGenerateAction;
use App\Modules\X103\Actions\SiteDesignUseAction;
use App\Modules\X103\Actions\SiteEditApplyAction;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Designs one page with one AI in the background: a whole page takes one to three minutes, past a web request's limit.
 *
 * Building a whole site (SiteBuildWholeAction) also sets $applyIfEmpty: when the page has no sections yet, the design goes
 * straight onto it through the normal Use + Apply path (so Undo and Publish behave as for any AI change). A page that
 * already has sections is never overwritten — its design waits for the owner to use it.
 */
final class SiteDesignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 420;

    public function __construct(
        public readonly int $businessId,
        public readonly int $pageId,
        public readonly string $engine = 'claude',
        public readonly bool $applyIfEmpty = false,
        public readonly bool $keepSiteTheme = false,
    ) {}

    public function handle(SiteDesignGenerateAction $action): void
    {
        // A whole page is a long answer; the shared 60-second vendor timeout would cut it off.
        config(['ai.timeout' => 360]);

        Tenancy::actingAs($this->businessId, function () use ($action): void {
            try {
                $result = $action->handle($this->businessId, $this->pageId, $this->engine, $this->keepSiteTheme);
                if ($this->applyIfEmpty && ($result['status'] ?? null) === 'ready') {
                    $page = Page::where('business_id', $this->businessId)->find($this->pageId);
                    if ($page !== null && empty($page->draft_blocks) && ! isset($page->draft_meta['pending_edit'])) {
                        $used = app(SiteDesignUseAction::class)->handle($this->businessId, $this->pageId, $this->engine);
                        if (($used['status'] ?? null) === 'proposed') {
                            app(SiteEditApplyAction::class)->handle($this->businessId, $this->pageId);
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('an AI page design failed', [
                    'business_id' => $this->businessId,
                    'page_id' => $this->pageId,
                    'engine' => $this->engine,
                    'error' => $e->getMessage(),
                ]);
                $page = Page::where('business_id', $this->businessId)->find($this->pageId);
                if ($page !== null) {
                    $action->fail($page, $this->engine, 'error');
                }
            }
        });
    }
}
