<?php

declare(strict_types=1);

namespace App\Modules\X103\Ui;

use App\Models\Location;
use App\Modules\X103\Actions\SiteCrawlAction;
use App\Modules\X103\Actions\SiteDraftAction;
use App\Modules\X103\Actions\SiteImagesCopyAction;
use App\Modules\X103\Actions\SiteMissingFactsAction;
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
    public array $hours = [];

    public function mount(): void
    {
        $tenantId = Tenancy::id();
        $location = $tenantId ? Location::where('business_id', $tenantId)->first() : null;
        $stored = $location && is_array($location->opening_hours) ? $location->opening_hours : [];
        $storedByDay = [];
        foreach ($stored as $row) {
            $storedByDay[$row['day']] = $row;
        }

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $day) {
            $s = $storedByDay[$day] ?? null;
            $this->hours[] = [
                'day' => $day,
                'open' => $s && $s['open'] !== 'Closed' ? $s['open'] : '',
                'close' => $s['close'] ?? '',
                'closed' => $s && $s['open'] === 'Closed',
            ];
        }
    }

    public function saveHours(): void
    {
        $tenantId = Tenancy::idOrFail();
        $location = Location::where('business_id', $tenantId)->first();
        if ($location === null) {
            $this->dispatch('toast', message: 'Add a location first.');

            return;
        }
        $rows = [];
        foreach ($this->hours as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }
            if (! empty($row['closed'])) {
                $rows[] = ['day' => $day, 'open' => 'Closed', 'close' => ''];

                continue;
            }
            $open = trim((string) ($row['open'] ?? ''));
            $close = trim((string) ($row['close'] ?? ''));
            if ($open === '' && $close === '') {
                continue;
            }
            if (! preg_match('/^\d{2}:\d{2}$/', $open) || ! preg_match('/^\d{2}:\d{2}$/', $close)) {
                $this->dispatch('toast', message: "Use HH:MM for {$day}.");

                return;
            }
            $rows[] = ['day' => $day, 'open' => $open, 'close' => $close];
        }
        $location->opening_hours = $rows;
        $location->save();
        $this->dispatch('toast', message: count($rows) === 0 ? 'Hours cleared.' : 'Hours saved — the next draft shows them in the contact section.');
    }

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

        $location = $tenantId ? Location::where('business_id', $tenantId)->first() : null;
        $missing = $tenantId ? app(SiteMissingFactsAction::class)->handle((int) $tenantId, $location) : [];

        return view('x-103::site-inventory', [
            'pages' => $pages,
            'images' => $images,
            'draftPages' => $draftPages,
            'missing' => $missing,
        ]);
    }
}
