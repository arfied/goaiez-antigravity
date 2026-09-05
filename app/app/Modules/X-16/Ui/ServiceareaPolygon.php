<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Actions\MapsPolygonAction;
use App\Modules\X16\Models\ServicePolygon;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ServiceareaPolygon extends Component
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

    public string $name = '';

    public string $pointsText = '';

    public ?string $error = null;

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function define(MapsPolygonAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->error = null;

        if (trim($this->name) === '') {
            $this->error = 'Name cannot be empty';

            return;
        }

        $lines = array_filter(array_map('trim', explode("\n", $this->pointsText)));
        $points = [];
        foreach ($lines as $line) {
            if (strpos($line, ',') !== false) {
                [$lat, $lng] = explode(',', $line, 2);
                $points[] = [(float) $lat, (float) $lng];
            }
        }

        try {
            $action->define($this->businessId, $this->name, $points);
            $this->name = '';
            $this->pointsText = '';
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function toggle(int $polygonId, MapsPolygonAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $polygon = ServicePolygon::where('business_id', $this->businessId)->find($polygonId);
        if ($polygon) {
            $action->setActive($this->businessId, $polygonId, ! $polygon->is_active);
        }
    }

    public function render()
    {
        if ($this->isSample) {
            $polygons = collect([
                (object) ['id' => 1, 'polygon_name' => 'Downtown Area', 'is_active' => true, 'coordinates' => [[41.8781, -87.6298], [41.8782, -87.6299], [41.8783, -87.6297]]],
                (object) ['id' => 2, 'polygon_name' => 'North Side', 'is_active' => false, 'coordinates' => [[41.9781, -87.6298], [41.9782, -87.6299], [41.9783, -87.6297]]],
            ]);
        } else {
            $polygons = ($this->businessId > 0)
                ? ServicePolygon::where('business_id', $this->businessId)->orderBy('id')->get()
                : collect();
        }

        return view('x-16::servicearea-polygon', [
            'polygons' => $polygons,
        ]);
    }
}
