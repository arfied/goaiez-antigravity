<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Modules\CBilling\Actions\LedgerExplainAction;
use App\Modules\CBilling\Actions\TopupChargeAction;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\DunningState;
use App\Modules\CBilling\Models\Meter;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Credits'])]
class Credits extends Component
{
    use LabelsMeters;
    use SignalsLedgerEntry;

    public ?int $explainedEntryId = null;

    public ?array $explanation = null;

    public ?string $error = null;

    public ?string $success = null;

    public function explain(int $entryId, LedgerExplainAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $this->explanation = $action->handle(Tenancy::idOrFail(), $entryId);
            $this->explainedEntryId = $entryId;
        } catch (ModelNotFoundException) {
            $this->error = "That entry isn't in this account any more.";
            $this->explainedEntryId = null;
            $this->explanation = null;
        } catch (\Throwable $e) {
            $this->error = 'Error explaining entry: '.$e->getMessage();
            $this->explainedEntryId = null;
            $this->explanation = null;
        }
    }

    public function topup(TopupChargeAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $result = $action->handle(Tenancy::idOrFail(), 5000);
            if ($result['status'] === 'charged') {
                $this->success = number_format($result['charged_amount_cents'] / 100, 2).' added to the top-up ledger. It is separate from the credit your plan includes and the credit you buy. Nothing was charged: this button grants credit, and the paid top-up that takes a card is not wired to it yet.';
            } else {
                $this->error = $result['message'] ?? 'Top-up refused.';
            }
        } catch (\DomainException) {
            $this->error = 'That top-up would pass the daily top-up ceiling on this account, so nothing was added. The ceiling resets tomorrow.';
        } catch (\Throwable $e) {
            $this->error = 'Error topping up: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $dunning = DunningState::where('business_id', Tenancy::id())->first();
        $meters = Meter::where('business_id', Tenancy::id())
            ->orderBy('meter_type')
            ->get()
            ->keyBy('meter_type');
        $entries = CreditLedgerEntry::where('business_id', Tenancy::id())
            ->orderByDesc('id')
            ->get();
        $latestEntry = $entries->first();
        $aiBalance = $latestEntry ? $latestEntry->balance_after_hundredths_cents : 0;

        $meterLabels = $this->meterLabels();

        return view('c-billing::credits', [
            'meters' => $meters,
            'entries' => $entries,
            'aiBalance' => $aiBalance,
            'meterLabels' => $meterLabels,
            'dunning' => $dunning,
            'ledgerEntryPillStates' => $this->ledgerEntryPillStates(),
        ]);
    }
}
