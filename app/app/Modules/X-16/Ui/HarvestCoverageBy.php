<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Models\PlacesRecord;
use App\Modules\X16\Models\ServicePolygon;
use App\Services\Places\GooglePlacesClient;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Harvest coverage'])]
class HarvestCoverageBy extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $this->businessId = Tenancy::id() ?: 0;
            if ($this->businessId <= 0) {
                abort(403, 'Tenant context is required');
            }
        }
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function render()
    {
        if ($this->isSample) {
            $rows = [
                (object) ['polygon_name' => 'Downtown', 'places_inside' => 12],
                (object) ['polygon_name' => 'North side', 'places_inside' => 5],
                (object) ['polygon_name' => 'Outside every territory', 'places_inside' => 2],
                (object) ['polygon_name' => 'No coordinates', 'places_inside' => 1],
            ];
            $placesTotal = 20;
            $territoriesTotal = 2;
            $hasPlaces = true;
        } else {
            $records = ($this->businessId > 0)
                ? PlacesRecord::where('business_id', $this->businessId)->where('is_chain', false)->get()->all()
                : [];
            $polygons = ($this->businessId > 0)
                ? ServicePolygon::where('business_id', $this->businessId)->where('is_active', true)->orderBy('id')->get()->all()
                : [];

            $placesTotal = count($records);
            $territoriesTotal = count($polygons);
            $hasPlaces = $placesTotal > 0;

            $rows = [];
            $usedRecordIds = [];

            foreach ($polygons as $polygon) {
                $coords = $polygon->coordinates;
                $lats = array_column($coords, 0);
                $lngs = array_column($coords, 1);
                $minLat = min($lats);
                $maxLat = max($lats);
                $minLng = min($lngs);
                $maxLng = max($lngs);

                $inside = 0;
                foreach ($records as $record) {
                    if ($record->latitude !== null && $record->longitude !== null) {
                        if ($record->latitude >= $minLat && $record->latitude <= $maxLat && $record->longitude >= $minLng && $record->longitude <= $maxLng) {
                            $inside++;
                            $usedRecordIds[$record->id] = true;
                        }
                    }
                }

                $rows[] = (object) [
                    'polygon_name' => (string) $polygon->polygon_name,
                    'places_inside' => $inside,
                ];
            }

            $outside = 0;
            $noCoords = 0;
            foreach ($records as $record) {
                if ($record->latitude === null || $record->longitude === null) {
                    $noCoords++;
                } else {
                    if (! isset($usedRecordIds[$record->id])) {
                        $outside++;
                    }
                }
            }

            $rows[] = (object) [
                'polygon_name' => 'Outside every territory',
                'places_inside' => $outside,
            ];
            $rows[] = (object) [
                'polygon_name' => 'No coordinates',
                'places_inside' => $noCoords,
            ];
        }

        return view('x-16::harvest-coverage-by', [
            'rows' => $rows,
            'placesTotal' => $placesTotal,
            'territoriesTotal' => $territoriesTotal,
            'hasPlaces' => $hasPlaces,
            'keyInUse' => app(GooglePlacesClient::class)->keyInUse(),
        ]);
    }
}
