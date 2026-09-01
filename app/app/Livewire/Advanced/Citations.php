<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Citation;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Citations extends Component
{
    public string $selectedDirectory = '';

    public string $newUrl = '';

    public bool $isScanning = false;

    public string $scanMessage = '';

    public int $healthPercentage = 0;

    public int $totalCount = 0;

    public int $consistentCount = 0;

    public int $mismatchCount = 0;

    public int $missingCount = 0;

    public function mount(): void
    {
        $this->calculateHealth();
    }

    public function calculateHealth(): void
    {
        $businessId = Tenancy::id();
        $citations = Citation::where('business_id', $businessId)->get();

        $this->totalCount = $citations->count();
        $this->consistentCount = $citations->where('nap_status', 'consistent')->count();
        $this->mismatchCount = $citations->where('nap_status', 'mismatch')->count();
        $this->missingCount = $citations->where('nap_status', 'missing')->count();

        $this->healthPercentage = $this->totalCount > 0 ? (int) round(($this->consistentCount / $this->totalCount) * 100) : 0;
    }

    public function runScan(): void
    {
        $this->isScanning = true;
        $businessId = Tenancy::id();

        Citation::where('business_id', $businessId)->update([
            'last_checked_at' => Carbon::now(),
        ]);

        $this->calculateHealth();
        $this->scanMessage = '';
        $this->isScanning = false;
    }

    public function markResolved(int $citationId): void
    {
        $businessId = Tenancy::id();
        $citation = Citation::where('business_id', $businessId)->find($citationId);
        if ($citation) {
            $citation->update([
                'nap_status' => 'consistent',
                'mismatch_details' => null,
                'last_checked_at' => Carbon::now(),
            ]);
            $this->calculateHealth();
            $this->scanMessage = '';
        }
    }

    public function render(): View
    {
        $businessId = Tenancy::id();
        $citations = Citation::where('business_id', $businessId)->orderBy('directory')->get();
        $this->calculateHealth();

        return view('livewire.advanced.citations', [
            'citations' => $citations,
            'totalCount' => $this->totalCount,
            'consistentCount' => $this->consistentCount,
            'mismatchCount' => $this->mismatchCount,
            'missingCount' => $this->missingCount,
            'healthPercentage' => $this->healthPercentage,
        ]);
    }
}
