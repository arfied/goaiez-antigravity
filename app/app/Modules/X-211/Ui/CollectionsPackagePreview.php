<?php

declare(strict_types=1);

namespace App\Modules\X211\Ui;

use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X211\Actions\ArPackageForCollectionsAction;
use App\Modules\X211\Domain\NoResolutionAttemptException;
use App\Modules\X211\Models\ArCollectionsPackage;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\OfflinePayment;
use App\Modules\X211\Models\PaymentPlan;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Collections package'])]
class CollectionsPackagePreview extends Component
{
    public ?string $error = null;

    public ?string $success = null;

    public function package(int $invoiceId, ArPackageForCollectionsAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $businessId = Tenancy::idOrFail();

        $userId = auth()->id();
        if ($userId === null) {
            $this->error = 'Sending an account to collections is a human action — sign in as the owner first.';

            return;
        }

        try {
            $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);
            $action->handle($businessId, $invoiceId, (int) $userId);
            $this->success = $invoice->invoice_number.' packaged for collections.';
        } catch (NoResolutionAttemptException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That invoice isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not package that: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $bizId = Tenancy::idOrFail();

        $packages = ArCollectionsPackage::where('business_id', $bizId)->latest('id')->get();
        $packagedIds = $packages->pluck('invoice_id')->all();

        $candidates = app(InvoiceReader::class)->openOverdueForBusiness($bizId, $packagedIds);

        foreach ($candidates as $inv) {
            $inv->balance_cents = $inv->total_cents - $inv->paid_cents;
            $inv->days_overdue = (int) $inv->due_date->diffInDays(today());
            $inv->attempts = ArDunningAction::where('business_id', $bizId)->where('invoice_id', $inv->id)->count()
                + PaymentPlan::where('business_id', $bizId)->where('invoice_id', $inv->id)->count()
                + OfflinePayment::where('business_id', $bizId)->where('invoice_id', $inv->id)->count();
        }

        return view('x-211::collections-package-preview', [
            'candidates' => $candidates,
            'packages' => $packages,
        ]);
    }
}
