<?php

declare(strict_types=1);

namespace App\Modules\X201\Ui;

use App\Modules\X201\Actions\DisputeNoteAction;
use App\Modules\X201\Actions\DisputeSubmitAction;
use App\Modules\X201\Domain\DisputeAlreadySubmittedException;
use App\Modules\X201\Domain\DisputeNotCompiledException;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Disputes'])]
class DisputeCard extends Component
{
    /** @var array<int,string> keyed by dispute id */
    public array $note = [];

    public ?string $error = null;

    public ?string $success = null;

    public function addNote(int $disputeId, DisputeNoteAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $businessId = Tenancy::idOrFail();
            $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);
            $result = $action->handle($businessId, $disputeId, (string) ($this->note[$disputeId] ?? ''));
            $this->success = sprintf('Added to the bundle: %d evidence items for invoice #%d.', DisputeEvidence::where('business_id', $businessId)->where('dispute_id', $dispute->id)->count(), $dispute->invoice_id);
            unset($this->note[$disputeId]);
        } catch (DisputeAlreadySubmittedException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That dispute isn't in this account any more.";
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable $e) {
            $this->error = 'We could not add that note: '.$e->getMessage();
        }
    }

    public function approve(int $disputeId, DisputeSubmitAction $action): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $businessId = Tenancy::idOrFail();
            $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);
            $action->handle($businessId, $disputeId);
            $this->success = sprintf('Defence for invoice #%d is sealed and recorded here. Nothing was sent: filing it waits on the gateway chargeback contract.', $dispute->invoice_id);
        } catch (DisputeNotCompiledException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That dispute isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not submit that defence: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $disputes = Dispute::where('business_id', $businessId)->orderByDesc('id')->get();
        $evidence = DisputeEvidence::where('business_id', $businessId)->whereIn('dispute_id', $disputes->pluck('id')->all())->orderBy('id')->get()->groupBy('dispute_id');

        foreach ($disputes as $d) {
            $items = $evidence->get($d->id, collect());
            $d->evidence_items = $items;
            $d->evidence_count = $items->count();
            $d->is_open = in_array($d->status, ['opened', 'compiled'], true);
        }

        return view('x-201::dispute-card', ['disputes' => $disputes]);
    }
}
