<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Actions\MapsPolygonAction;
use App\Modules\X16\Models\ServicePolygon;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Service area'])]
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

    public function spans(array $coordinates): string
    {
        $lats = array_column($coordinates, 0);
        $lngs = array_column($coordinates, 1);

        return sprintf(
            'Spans %s…%s lat · %s…%s lng',
            number_format((float) min($lats), 4),
            number_format((float) max($lats), 4),
            number_format((float) min($lngs), 4),
            number_format((float) max($lngs), 4),
        );
    }

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
                (object) ['id' => 9991, 'polygon_name' => 'Downtown service area', 'is_active' => true, 'coordinates' => [[41.80, -87.70], [41.90, -87.70], [41.90, -87.60], [41.80, -87.60]]],
                (object) ['id' => 9992, 'polygon_name' => 'North side', 'is_active' => false, 'coordinates' => [[41.95, -87.75], [42.05, -87.75], [42.05, -87.65], [41.95, -87.65]]],
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
