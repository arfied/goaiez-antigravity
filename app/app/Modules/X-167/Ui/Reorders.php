<?php

declare(strict_types=1);

namespace App\Modules\X167\Ui;

use App\Enums\UserRole;
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Models\Supplier;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Reorders'])]
class Reorders extends Component
{
    #[Locked]
    public int $businessId;

    public array $expanded = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function toggle(int $id): void
    {
        if (isset($this->expanded[$id])) {
            unset($this->expanded[$id]);
        } else {
            $this->expanded[$id] = true;
        }
    }

    public function render()
    {
        $orders = PurchaseOrder::where('business_id', $this->businessId)
            ->orderByDesc('id')
            ->get();

        $supplierIds = $orders->pluck('supplier_id')->filter()->unique()->toArray();
        $suppliers = Supplier::whereIn('id', $supplierIds)->get()->keyBy('id');

        $money = [];
        $count = [];
        $pill = [];
        $itemPrices = [];

        foreach ($orders as $po) {
            $money[$po->id] = number_format($po->total_cents / 100, 2, '.', '');
            $items = is_array($po->items) ? $po->items : (json_decode($po->items ?? '[]', true) ?? []);
            $count[$po->id] = count($items);

            $status = $po->status;
            if ($status === 'proposed') {
                $pill[$po->id] = ['attention', 'Proposed'];
            } elseif ($status === 'approved') {
                $pill[$po->id] = ['ok', 'Approved'];
            } else {
                $pill[$po->id] = ['ok', 'Sent'];
            }

            foreach ($items as $idx => $item) {
                if (isset($item['unit_price_cents'])) {
                    $itemPrices[$po->id][$idx] = number_format($item['unit_price_cents'] / 100, 2, '.', '');
                }
            }
        }

        return view('x-167::reorders', [
            'orders' => $orders,
            'suppliers' => $suppliers,
            'money' => $money,
            'count' => $count,
            'pill' => $pill,
            'itemPrices' => $itemPrices,
        ]);
    }
}
