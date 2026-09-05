<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Models\Subscription;
use App\Modules\CBilling\Actions\LedgerExplainAction;
use App\Modules\CBilling\Actions\TopupChargeAction;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\Meter;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class Mrr extends Component
{
    use ReadsAgreedMonthly;

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
            $this->error = 'We could not explain that entry: '.$e->getMessage();
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
            if (($result['status'] ?? null) === 'charged') {
                $this->success = 'Topped up '.number_format($result['charged_amount_cents'] / 100, 2).'.';
            } else {
                $this->error = $result['message'] ?? 'Top-up refused.';
            }
        } catch (\Throwable $e) {
            $this->error = 'We could not top up: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $sub = Subscription::where('business_id', $businessId)->first();
        $monthly = $this->agreedMonthly($sub);

        $meters = Meter::where('business_id', $businessId)->orderBy('meter_type')->get();
        $entries = CreditLedgerEntry::where('business_id', $businessId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->orderByDesc('id')
            ->get();

        return view('c-billing::mrr', [
            'sub' => $sub,
            'monthly' => $monthly,
            'meters' => $meters,
            'entries' => $entries,
        ]);
    }
}
