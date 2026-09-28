<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Models\PlatformSetting;
use App\Modules\X199\Actions\InvoiceOverdueListAction;
use App\Modules\X211\Actions\ArDunningHistoryAction;
use App\Modules\X211\Actions\ArRecordReasonAction;
use App\Modules\X211\Domain\ArEngine;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Payment risk'])]
class PaymentRisk extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $message = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function recordReason(int $invoiceId, string $reasonCode): void
    {
        abort_if($this->businessId === 0, 403);

        $action = app(ArRecordReasonAction::class)->handle($this->businessId, $invoiceId, $reasonCode);
        $this->message = $action->reason;
    }

    public function render()
    {
        abort_if($this->businessId === 0, 403);

        $invoices = app(InvoiceOverdueListAction::class)->handle($this->businessId);

        $threshold = PlatformSetting::readInt('billing.risk.days_overdue_high', 30);

        $customerGroups = [];

        foreach ($invoices as $invoice) {
            $history = app(ArDunningHistoryAction::class)->handle($this->businessId, (int) $invoice['invoice_id']);
            $latestDunning = $history[0]['label'] ?? null;

            $risk = $invoice['days_overdue'] >= $threshold ? 'High' : 'Watch';

            $customerId = $invoice['customer_id'];
            if (! isset($customerGroups[$customerId])) {
                $customerGroups[$customerId] = [
                    'customer_name' => $invoice['customer_name'],
                    'invoices' => [],
                ];
            }

            $invoice['latest_dunning'] = $latestDunning;
            $invoice['risk'] = $risk;

            $customerGroups[$customerId]['invoices'][] = $invoice;
        }

        return view('x-01::payment-risk', [
            'customerGroups' => $customerGroups,
            'reasons' => ArEngine::REASONS,
        ]);
    }
}
