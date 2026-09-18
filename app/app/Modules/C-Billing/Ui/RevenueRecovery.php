<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Models\Subscription;
use App\Modules\CBilling\Actions\DunningAdvanceAction;
use App\Modules\CBilling\Actions\TopupChargeAction;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\DunningState;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Revenue recovery'])]
class RevenueRecovery extends Component
{
    use LabelsDunning;
    use ReadsAgreedMonthly;

    public ?string $error = null;

    public ?string $success = null;

    public function topupNow(int $stateId, TopupChargeAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            DunningState::where('business_id', Tenancy::idOrFail())->findOrFail($stateId);
            $result = $action->handle(Tenancy::idOrFail(), 5000);
            if (($result['status'] ?? null) === 'charged') {
                $this->success = number_format($result['charged_amount_cents'] / 100, 2).' added to the top-up ledger. It is separate from the credit your plan includes and the credit you buy. Nothing was charged: this button grants credit, and the paid top-up that takes a card is not wired to it yet.';
            } else {
                $this->error = $result['message'] ?? 'Top-up refused.';
            }
        } catch (ModelNotFoundException) {
            $this->error = "That case isn't in this account any more.";
        } catch (\DomainException) {
            $this->error = 'That top-up would pass the daily top-up ceiling on this account, so nothing was added. The ceiling resets tomorrow.';
        } catch (\Throwable $e) {
            $this->error = 'We could not top up: '.$e->getMessage();
        }
    }

    public function advance(int $stateId, DunningAdvanceAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $state = DunningState::where('business_id', Tenancy::idOrFail())->findOrFail($stateId);
            $action->handle(Tenancy::idOrFail(), $state->day_in_cycle + 1);
        } catch (ModelNotFoundException) {
            $this->error = "That case isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not advance the ladder: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $monthly = $this->agreedMonthly(Subscription::where('business_id', $businessId)->first());

        $states = DunningState::where('business_id', $businessId)
            ->where('status', '!=', 'active')
            ->orderByDesc('day_in_cycle')
            ->get();

        foreach ($states as $state) {
            $recoveredHundredths = (int) CreditLedgerEntry::where('business_id', $businessId)
                ->whereIn('entry_type', ['topup', 'grant'])
                ->where('created_at', '>=', $state->created_at)
                ->sum('amount_hundredths_cents');
            $state->recovered_cents = intdiv($recoveredHundredths, 100);
            $state->stays_on = implode(' · ', array_filter([
                $state->phone_answering ? 'phone answers' : null,
                $state->ai_enabled ? 'AI on' : 'AI off',
                $state->voicemail_only ? 'voicemail only' : null,
            ]));
        }

        return view('c-billing::revenue-recovery', [
            'states' => $states,
            'monthly' => $monthly,
            'dunningLabels' => $this->dunningLabels(),
        ]);
    }
}
