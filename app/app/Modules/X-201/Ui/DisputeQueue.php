<?php

declare(strict_types=1);

namespace App\Modules\X201\Ui;

use App\Modules\X201\Actions\DisputeCompileAction;
use App\Modules\X201\Actions\DisputeOutcomeAction;
use App\Modules\X201\Actions\DisputeSubmitAction;
use App\Modules\X201\Domain\DisputeNotCompiledException;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Dispute queue'])]
class DisputeQueue extends Component
{
    /** @var array<int,string> keyed by dispute id */
    public array $note = [];

    public ?string $error = null;

    public ?string $success = null;

    public function compile(int $disputeId, DisputeCompileAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $businessId = Tenancy::idOrFail();
            $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);
            $items = [['type' => 'invoice', 'content' => sprintf('Invoice #%d — %s disputed as %s', $dispute->invoice_id, number_format($dispute->chargeback_amount_cents / 100, 2), $dispute->reason)]];
            $note = trim((string) ($this->note[$disputeId] ?? ''));
            if ($note !== '') {
                $items[] = ['type' => 'note', 'content' => $note];
            }
            $result = $action->handle($businessId, $disputeId, $items);
            $this->success = sprintf('Compiled %d evidence items for invoice #%d.', $result['evidence_count'], $dispute->invoice_id);
            unset($this->note[$disputeId]);
        } catch (ModelNotFoundException) {
            $this->error = "That dispute isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not compile that dispute: '.$e->getMessage();
        }
    }

    public function submit(int $disputeId, DisputeSubmitAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $dispute = Dispute::where('business_id', Tenancy::idOrFail())->findOrFail($disputeId);
            $action->handle(Tenancy::idOrFail(), $disputeId);
            $this->success = sprintf('Defence for invoice #%d is sealed and recorded here. Nothing was sent: filing it waits on the gateway chargeback contract.', $dispute->invoice_id);
        } catch (DisputeNotCompiledException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That dispute isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not submit that dispute: '.$e->getMessage();
        }
    }

    public function outcome(int $disputeId, string $outcome, DisputeOutcomeAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $businessId = Tenancy::idOrFail();
            $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);
            $result = $action->handle($businessId, $disputeId, $outcome);
            $this->success = sprintf('Recorded: invoice #%d %s.', $dispute->invoice_id, $outcome);
            if ($result['commission_clawback_triggered']) {
                $this->success .= ' Commission clawback flagged: no commission has been taken back, because nothing acts on that flag yet.';
            }
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That dispute isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not record that outcome: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $disputes = Dispute::where('business_id', $businessId)->whereNotIn('status', ['won', 'lost'])->orderByDesc('id')->get();
        $evidence = DisputeEvidence::where('business_id', $businessId)->whereIn('dispute_id', $disputes->pluck('id')->all())->orderBy('id')->get()->groupBy('dispute_id');

        foreach ($disputes as $d) {
            $items = $evidence->get($d->id, collect());
            $d->evidence_items = $items;
            $d->evidence_count = $items->count();
            $d->has_signature = $items->contains('evidence_type', 'signature');
        }

        return view('x-201::dispute-queue', ['disputes' => $disputes]);
    }
}
