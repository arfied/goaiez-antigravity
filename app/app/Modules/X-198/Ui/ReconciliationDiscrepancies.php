<?php

declare(strict_types=1);

namespace App\Modules\X198\Ui;

use App\Models\User;
use App\Modules\X198\Actions\DiscrepancyReviewAction;
use App\Modules\X198\Domain\NothingToReviewException;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Reconciliation discrepancies'])]
class ReconciliationDiscrepancies extends Component
{
    public ?string $error = null;

    public ?string $success = null;

    public function review(int $runId, DiscrepancyReviewAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $run = $action->handle(Tenancy::idOrFail(), $runId, (int) auth()->id());
            $this->success = sprintf(
                'Reviewed: payout %s stays %s off; the run is never corrected.',
                $this->payoutLabel($run),
                number_format(abs($run->discrepancy_cents) / 100, 2)
            );
        } catch (NothingToReviewException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That run isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not mark that run reviewed: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $runs = ReconciliationRun::where('business_id', $businessId)
            ->where('status', 'discrepancy_logged')
            ->orderByDesc('id')
            ->get();

        $payouts = Payout::where('business_id', $businessId)
            ->whereIn('id', $runs->pluck('payout_id')->filter()->all())
            ->get()
            ->keyBy('id');
        $reviewers = User::whereIn('id', $runs->pluck('reviewed_by_user_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($runs as $run) {
            $payout = $payouts[$run->payout_id] ?? null;
            $run->payout_label = $payout->gateway_payout_id ?? 'payout #'.$run->payout_id;
            $run->payout_date = $payout?->payout_date?->toDateString();
            $run->reviewed_label = $run->reviewed_at === null
                ? null
                : sprintf(
                    'reviewed by %s on %s',
                    $reviewers[$run->reviewed_by_user_id]->name ?? 'user #'.$run->reviewed_by_user_id,
                    $run->reviewed_at->toDateString()
                );
        }

        return view('x-198::reconciliation-discrepancies', ['runs' => $runs]);
    }

    private function payoutLabel(ReconciliationRun $run): string
    {
        $payout = Payout::where('business_id', Tenancy::idOrFail())->find($run->payout_id);

        return $payout->gateway_payout_id ?? 'payout #'.$run->payout_id;
    }
}
