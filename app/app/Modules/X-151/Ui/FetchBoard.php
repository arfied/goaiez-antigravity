<?php

declare(strict_types=1);

namespace App\Modules\X151\Ui;

use App\Modules\X151\Actions\FetchRefreshAction;
use App\Modules\X151\Models\Fetch;
use App\Modules\X151\Models\FetchTarget;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Fetch board'])]
class FetchBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public string $tab = 'all';

    public ?string $actionNotice = null;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $tenantId = Tenancy::id() ?: 0;
            if ($tenantId <= 0) {
                abort(403, 'Tenant context is required');
            }
            $this->businessId = (int) $tenantId;
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->actionNotice = null;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function markStale(int $fetchId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(FetchRefreshAction::class)->handle($this->businessId, $fetchId);
            $this->actionNotice = 'Marked stale';
        } catch (\Exception $e) {
            $this->actionNotice = $e->getMessage();
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        if ($this->isSample) {
            $targets = collect([
                9300 => (object) [
                    'id' => 9300,
                    'domain' => 'example-plumbing.test',
                    'rps_ceiling' => 2,
                    'concurrency_ceiling' => 5,
                ],
            ]);

            $fetches = collect([
                (object) [
                    'id' => 9301,
                    'url' => 'https://example-plumbing.test/services',
                    'status' => 'success',
                    'is_stale' => false,
                    'captcha_attempts' => 0,
                    'updated_at' => now()->subMinutes(5),
                    'target_id' => 9300,
                ],
                (object) [
                    'id' => 9302,
                    'url' => 'https://example-plumbing.test/pricing',
                    'status' => 'skipped_captcha',
                    'is_stale' => false,
                    'captcha_attempts' => 3,
                    'updated_at' => now()->subMinutes(15),
                    'target_id' => 9300,
                ],
                (object) [
                    'id' => 9303,
                    'url' => 'https://example-plumbing.test/about',
                    'status' => 'stale',
                    'is_stale' => true,
                    'captcha_attempts' => 0,
                    'updated_at' => now()->subMinutes(60),
                    'target_id' => 9300,
                ],
            ]);
        } else {
            $targets = FetchTarget::where('business_id', $this->businessId)
                ->get()
                ->keyBy('id');

            $fetches = Fetch::where('business_id', $this->businessId);
        }

        if ($this->tab === 'queued') {
            $fetches = $this->isSample ? $fetches->filter(fn ($f) => in_array($f->status, ['queued', 'queued_ceiling_held'])) : $fetches->whereIn('status', ['queued', 'queued_ceiling_held']);
        } elseif ($this->tab === 'blocked') {
            $fetches = $this->isSample ? $fetches->where('status', 'skipped_captcha') : $fetches->where('status', 'skipped_captcha');
        } elseif ($this->tab === 'stale') {
            $fetches = $this->isSample ? $fetches->where('is_stale', true) : $fetches->where('is_stale', true);
        }

        if (! $this->isSample) {
            $fetches = $fetches->orderBy('updated_at', 'desc')->take(50)->get();
        }

        return view('x-151::fetch-board', [
            'targets' => $targets,
            'fetches' => $fetches,
        ]);
    }
}
