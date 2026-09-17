<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Actions\MapsGeogridAction;
use App\Modules\X16\Models\GeoGrid;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Geo-grid'])]
class GeogridMap extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public bool $actionFailed = false;

    public string $gridName = '';

    public string $centerLat = '';

    public string $centerLng = '';

    public int $radiusKm = 10;

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
        $this->actionFailed = false;
    }

    public function generate(): void
    {
        if ($this->isSample) {
            return;
        }

        $this->validate([
            'gridName' => 'required|string|max:80',
            'centerLat' => 'required|numeric|min:-90|max:90',
            'centerLng' => 'required|numeric|min:-180|max:180',
            'radiusKm' => 'required|integer|min:1|max:50',
        ]);

        Tenancy::set($this->businessId);

        try {
            app(MapsGeogridAction::class)->generateGrid(
                $this->businessId,
                $this->gridName,
                (float) $this->centerLat,
                (float) $this->centerLng,
                $this->radiusKm
            );
            $this->actionFailed = false;
            $this->gridName = '';
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function regenerate(int $id): void
    {
        if ($this->isSample) {
            return;
        }

        Tenancy::set($this->businessId);

        try {
            $grid = GeoGrid::where('business_id', $this->businessId)->findOrFail($id);
            app(MapsGeogridAction::class)->generateGrid(
                $this->businessId,
                $grid->grid_name,
                $grid->center_lat,
                $grid->center_lng,
                $grid->radius_km
            );
            $this->actionFailed = false;
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        if ($this->isSample) {
            $grids = collect([
                (object) [
                    'id' => 9991,
                    'grid_name' => 'Downtown Sample Grid',
                    'center_lat' => 41.8781,
                    'center_lng' => -87.6298,
                    'radius_km' => 10,
                    'points_total' => 25,
                    'points_scanned' => 5,
                    'last_scanned_at' => null,
                    'grid_points' => array_fill(0, 25, ['rank' => null]),
                ],
            ]);
            $grids[0]->grid_points[0]['rank'] = 1;
            $grids[0]->grid_points[1]['rank'] = 2;
            $grids[0]->grid_points[2]['rank'] = 1;
            $grids[0]->grid_points[3]['rank'] = 3;
            $grids[0]->grid_points[4]['rank'] = 2;
        } else {
            $dbGrids = GeoGrid::where('business_id', $this->businessId)->orderByDesc('id')->get()->all();
            $grids = collect($dbGrids)->map(function (GeoGrid $g): \stdClass {
                $points = is_array($g->grid_points) ? $g->grid_points : [];
                $scanned = 0;
                foreach ($points as $p) {
                    if (is_array($p) && array_key_exists('rank', $p) && $p['rank'] !== null) {
                        $scanned++;
                    }
                }

                return (object) [
                    'id' => (int) $g->id,
                    'grid_name' => (string) $g->grid_name,
                    'center_lat' => (float) $g->center_lat,
                    'center_lng' => (float) $g->center_lng,
                    'radius_km' => (int) $g->radius_km,
                    'points_total' => count($points),
                    'points_scanned' => $scanned,
                    'last_scanned_at' => null, // Nothing writes rank yet
                    'grid_points' => $points,
                ];
            });
        }

        $anyUnscanned = $grids->contains(fn ($g) => $g->points_scanned === 0);

        return view('x-16::geogrid-map', [
            'grids' => $grids,
            'anyUnscanned' => $anyUnscanned,
        ]);
    }
}
