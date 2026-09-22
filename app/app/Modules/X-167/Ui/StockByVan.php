<?php

declare(strict_types=1);

namespace App\Modules\X167\Ui;

use App\Enums\UserRole;
use App\Modules\X167\Actions\ReorderProposeAction;
use App\Modules\X167\Actions\StockItemCreateAction;
use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\StockLocation;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Stock by van'])]
class StockByVan extends Component
{
    #[Locked]
    public int $businessId;

    public array $proposed = [];

    public string $newName = '';

    public string $newSku = '';

    public string $newUnit = 'units';

    public string $newQuantity = '0';

    public string $newReorderPoint = '5';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function addItem(StockItemCreateAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $item = $action->handle(
                $this->businessId,
                $this->newName,
                $this->newSku,
                $this->newUnit,
                (float) $this->newQuantity,
                (float) $this->newReorderPoint,
            );
            $this->success = $item->name.' added.';
            $this->newName = '';
            $this->newSku = '';
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable $e) {
            $this->error = 'We could not add that item: '.$e->getMessage();
        }
    }

    public function proposeRestock(int $itemId): void
    {
        $item = StockItem::where('business_id', $this->businessId)->findOrFail($itemId);

        $po = app(ReorderProposeAction::class)->handle(
            $this->businessId,
            null,
            [[
                'stock_item_id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'qty' => (float) $item->reorder_point,
                'unit' => $item->unit,
            ]],
            0
        );

        $this->proposed[$item->id] = $po->po_number;
    }

    public function render()
    {
        $locations = StockLocation::where('business_id', $this->businessId)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $itemsGrouped = StockItem::where('business_id', $this->businessId)
            ->orderBy('name')
            ->get()
            ->groupBy('location_id');

        $qty = [];
        $point = [];
        $low = [];

        foreach ($itemsGrouped as $locId => $locItems) {
            foreach ($locItems as $item) {
                $q = (float) $item->quantity;
                $p = (float) $item->reorder_point;
                $qty[$item->id] = number_format($q, 2, '.', '').' '.$item->unit;
                $point[$item->id] = number_format($p, 2, '.', '').' '.$item->unit;
                $low[$item->id] = $q <= $p;
            }
        }

        return view('x-167::stock-by-van', [
            'locations' => $locations,
            'itemsGrouped' => $itemsGrouped,
            'qty' => $qty,
            'point' => $point,
            'low' => $low,
        ]);
    }
}
