<?php

declare(strict_types=1);

namespace App\Modules\X103\Ui;

use App\Models\Location;
use App\Modules\X103\Actions\SiteCrawlAction;
use App\Modules\X103\Actions\SiteDraftAction;
use App\Modules\X103\Actions\SiteImagesCopyAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your current website'])]
final class SiteInventory extends Component
{
    public function crawl(SiteCrawlAction $action): void
    {
        try {
            $tenantId = Tenancy::idOrFail();
            $location = Location::where('business_id', $tenantId)->first();

            $result = $action->handle($tenantId, $location ? $location->id : $tenantId);

            if ($result['status'] === 'refused') {
                $reason = $result['reason'] ?? 'unknown';
                $this->dispatch('toast', message: "Crawl refused: {$reason}");

                return;
            }

            $this->dispatch('toast', message: "Crawled {$result['pages']} pages, {$result['refused']} refused");
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function copyImages(SiteImagesCopyAction $action): void
    {
        try {
            $tenantId = Tenancy::idOrFail();
            $location = Location::where('business_id', $tenantId)->first();

            $result = $action->handle($tenantId, $location ? $location->id : $tenantId);

            $this->dispatch('toast', message: "Stored {$result['stored']}, skipped {$result['skipped']}, refused {$result['refused']}");
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function draftSite(SiteDraftAction $action): void
    {
        try {
            $tenantId = Tenancy::idOrFail();
            $location = Location::where('business_id', $tenantId)->first();

            $result = $action->handle($tenantId, $location ? $location->id : $tenantId);

            $skippedText = empty($result['skipped']) ? '' : ' (Skipped: '.implode(', ', $result['skipped']).')';
            $this->dispatch('toast', message: "Drafted {$result['pages']} pages with {$result['blocks']} blocks{$skippedText}");
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function render(): View
    {
        $tenantId = Tenancy::id();

        $pages = SiteInventoryPage::latest('fetched_at')->withCount(['images' => function ($query) {
            $query->where('status', 'stored');
        }])->get();

        $images = SiteInventoryImage::where('business_id', $tenantId)->get();

        $draftPages = Page::where('business_id', $tenantId)->get();

        return view('x-103::site-inventory', [
            'pages' => $pages,
            'images' => $images,
            'draftPages' => $draftPages,
        ]);
    }
}
