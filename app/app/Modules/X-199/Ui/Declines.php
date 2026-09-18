<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Actions\PaymentReadAction;
use App\Modules\X199\Actions\DeferDeclineAction;
use App\Modules\X199\Models\DeclineDeferral;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Payment Declines & Exceptions'])]
class Declines extends Component
{
    public bool $showAll = false;

    public ?string $error = null;

    public ?string $errorHeading = null;

    public function sendPayLink(int $paymentId, PaymentLinkAction $action): void
    {
        $this->error = null;
        $this->errorHeading = 'Could not make that pay link';
        try {
            $action->handle(Tenancy::idOrFail(), $paymentId);
        } catch (ModelNotFoundException) {
            $this->error = "That attempt isn't in this account any more — reload the list.";
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function settleUpLater(int $paymentId, DeferDeclineAction $action): void
    {
        $this->error = null;
        $this->errorHeading = 'Could not set that aside';
        try {
            $action->handle(Tenancy::idOrFail(), $paymentId);
        } catch (ModelNotFoundException) {
            $this->error = "That attempt isn't in this account any more — reload the list.";
        }
    }

    public function toggleShowAll(): void
    {
        $this->showAll = ! $this->showAll;
    }

    public function render(PaymentReadAction $paymentReader)
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $deferredPaymentIds = [];
        if (! $this->showAll) {
            $deferredPaymentIds = DeclineDeferral::where('business_id', Tenancy::id())
                ->pluck('payment_id')
                ->toArray();
        }

        $declines = $paymentReader->declined(Tenancy::id(), $this->showAll, $deferredPaymentIds);
        $declineIds = array_column($declines, 'id');

        $paymentLinks = $paymentReader->links(Tenancy::id(), $declineIds);

        $recoveredCounts = 0;
        foreach ($declines as &$decline) {
            $recovered = $paymentReader->recovered(Tenancy::id(), (string) $decline['payment_token'], (string) $decline['created_at']);

            $decline['recovered'] = $recovered;
            if ($recovered) {
                $recoveredCounts++;
            }

            $decline['deferred'] = DeclineDeferral::where('business_id', Tenancy::id())
                ->where('payment_id', $decline['id'])
                ->exists();

            $decline['pay_link'] = $paymentLinks[$decline['id']] ?? null;
        }

        return view('x-199::declines', [
            'declines' => $declines,
            'declinesCount' => count($declines),
            'recoveredCount' => $recoveredCounts,
        ]);
    }
}
