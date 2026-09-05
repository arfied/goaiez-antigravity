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
        Tenancy::set($this->businessId);
    }

    public string $name = '';

    public string $pointsText = '';

    public ?string $refusal = null;

    public bool $actionFailed = false;

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function define(MapsPolygonAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->refusal = null;
        $this->actionFailed = false;

        if (trim($this->name) === '') {
            $this->addError('name', 'Name cannot be empty');

            return;
        }

        $lines = array_filter(array_map('trim', explode("\n", $this->pointsText)));
        $points = [];
        foreach ($lines as $line) {
            if (strpos($line, ',') === false) {
                $this->addError('pointsText', 'One lat,lng pair per line');

                return;
            }
            [$lat, $lng] = explode(',', $line, 2);
            $points[] = [(float) $lat, (float) $lng];
        }

        try {
            $action->define($this->businessId, $this->name, $points);
            $this->name = '';
            $this->pointsText = '';
        } catch (\DomainException $e) {
            $this->refusal = $e->getMessage();
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function toggle(int $polygonId, MapsPolygonAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->refusal = null;
        $this->actionFailed = false;

        try {
            $current = ServicePolygon::where('business_id', $this->businessId)->find($polygonId);
            $nextState = $current ? ! $current->is_active : false;
            $action->setActive($this->businessId, $polygonId, $nextState);
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function render()
    {
        if ($this->isSample) {
            $polygons = collect([
                (object) ['id' => 1, 'polygon_name' => 'Downtown Area', 'is_active' => true, 'coordinates' => [[41.8781, -87.6298], [41.9782, -87.6299], [41.8783, -87.5297]]],
                (object) ['id' => 2, 'polygon_name' => 'North Side', 'is_active' => false, 'coordinates' => [[41.9781, -87.6298], [42.0782, -87.6299], [41.9783, -87.5297]]],
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
