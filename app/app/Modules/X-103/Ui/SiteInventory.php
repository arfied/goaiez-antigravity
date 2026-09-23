<?php

declare(strict_types=1);

namespace App\Modules\X103\Ui;

use App\Models\Location;
use App\Modules\X103\Actions\SiteCrawlAction;
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
            dump($e->getMessage(), $e->getTraceAsString());
            throw $e;
        }
    }

    public function render(): View
    {
        return view('x-103::site-inventory', [
            'pages' => SiteInventoryPage::latest('fetched_at')->get(),
        ]);
    }
}
