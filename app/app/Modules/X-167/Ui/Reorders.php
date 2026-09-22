<?php

declare(strict_types=1);

namespace App\Modules\X167\Ui;

use App\Enums\UserRole;
use App\Modules\X167\Actions\PoGenerateAction;
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Models\Supplier;
use App\Modules\X202\Actions\ApprovalEnqueueAction;
use App\Modules\X202\Actions\ApprovalLookupAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Reorders'])]
class Reorders extends Component
{
    #[Locked]
    public int $businessId;

    public ?string $error = null;

    public ?string $success = null;

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

    public function requestApproval(int $poId, ApprovalEnqueueAction $action): void
    {
        $this->error = null;
        $this->success = null;

        $po = PurchaseOrder::where('business_id', Tenancy::idOrFail())->findOrFail($poId);

        if ($po->status !== 'proposed') {
            $this->error = 'Only a proposed order can be sent for approval. This one reads '.$po->status.'.';

            return;
        }

        $result = $action->handle(
            Tenancy::idOrFail(),
            'purchase_order_send',
            'Send purchase order '.$po->po_number,
            ['purchase_order_id' => $po->id, 'po_number' => $po->po_number, 'total_cents' => $po->total_cents],
        );

        if (($result['status'] ?? null) !== 'enqueued') {
            $this->error = 'That approval could not be raised: '.($result['message'] ?? 'unknown reason');

            return;
        }

        $this->success = 'Approval requested for '.$po->po_number.'. It is waiting on the approvals queue. '
            .'Nothing is sent until somebody approves it there.';
    }

    public function sendPo(int $poId, ApprovalLookupAction $lookup, PoGenerateAction $action): void
    {
        $this->error = null;
        $this->success = null;

        $po = PurchaseOrder::where('business_id', Tenancy::idOrFail())->findOrFail($poId);

        if ($po->status !== 'proposed') {
            $this->error = 'Only a proposed order can be sent. This one reads '.$po->status.'.';

            return;
        }

        $approvalId = $lookup->approvedIdFor(
            Tenancy::idOrFail(),
            'purchase_order_send',
            'Send purchase order '.$po->po_number,
        );

        if ($approvalId === null) {
            $this->error = 'No approved approval exists for '.$po->po_number
                .'. Request approval first, then approve it on the approvals queue.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $po->id, (string) $approvalId);

        if (($result['status'] ?? null) !== 'sent') {
            $this->error = 'That order was not sent: '.($result['message'] ?? 'unknown reason');

            return;
        }

        $this->success = $po->po_number.' is marked sent, against approval #'.$approvalId
            .'. Nothing emails the supplier — this records the status and the approval it was sent under.';
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
