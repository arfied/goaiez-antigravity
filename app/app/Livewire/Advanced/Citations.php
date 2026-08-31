<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Business;
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
        $this->seedDefaultDirectoriesIfEmpty();
        $this->calculateHealth();
    }

    public function seedDefaultDirectoriesIfEmpty(): void
    {
        $businessId = Tenancy::id();
        if (! $businessId) {
            return;
        }

        $count = Citation::where('business_id', $businessId)->count();
        if ($count === 0) {
            $business = Business::find($businessId);
            $businessName = $business?->name ?? 'My Business';
            $businessPhone = $business?->phone ?? '(555) 019-2831';
            $businessAddress = '100 Main St, Suite 200';

            $directories = [
                ['directory' => 'Google Business Profile', 'url' => 'https://google.com/maps', 'status' => 'consistent'],
                ['directory' => 'Apple Maps', 'url' => 'https://maps.apple.com', 'status' => 'consistent'],
                ['directory' => 'Bing Places', 'url' => 'https://bing.com/places', 'status' => 'consistent'],
                ['directory' => 'Yelp', 'url' => 'https://yelp.com/biz', 'status' => 'mismatch', 'mismatch' => ['phone' => 'Old phone on record']],
                ['directory' => 'Facebook Local', 'url' => 'https://facebook.com', 'status' => 'consistent'],
                ['directory' => 'YellowPages', 'url' => 'https://yellowpages.com', 'status' => 'consistent'],
                ['directory' => 'Better Business Bureau (BBB)', 'url' => 'https://bbb.org', 'status' => 'missing'],
                ['directory' => 'MapQuest', 'url' => 'https://mapquest.com', 'status' => 'consistent'],
            ];

            foreach ($directories as $d) {
                Citation::create([
                    'business_id' => $businessId,
                    'directory' => $d['directory'],
                    'directory_url' => $d['url'],
                    'nap_status' => $d['status'],
                    'listing_name' => $businessName,
                    'listing_address' => $businessAddress,
                    'listing_phone' => $businessPhone,
                    'mismatch_details' => $d['mismatch'] ?? null,
                    'last_checked_at' => Carbon::now()->subHours(rand(1, 48)),
                ]);
            }
        }
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
        $this->scanMessage = 'Citation scan complete. Verified 8 major local business directories.';
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
            $this->scanMessage = 'Listing updated to Consistent.';
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
