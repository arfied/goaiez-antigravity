<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Actions\InfluencerDeliverableAction;
use App\Modules\X218\Models\Deliverable;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Deliverable proof'])]
class DeliverableProof extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $dealId = '';

    public string $liveUrl = '';

    public int $httpStatus = 200;

    public string $artifactHash = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function submitProof(InfluencerDeliverableAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->dealId)) {
            $this->error = 'Please provide a deal ID.';

            return;
        }

        try {
            $deliverable = $action->submitAndVerifyDeliverable(
                Tenancy::idOrFail(),
                (int) $this->dealId,
                $this->liveUrl,
                $this->httpStatus,
                $this->artifactHash ?: null
            );

            $this->success = 'Recorded deliverable for deal '.$this->dealId.' with the status you entered ('.$this->httpStatus.'). The app did not open the page itself — check the post before you pay out.';

            $this->dealId = '';
            $this->liveUrl = '';
            $this->httpStatus = 200;
            $this->artifactHash = '';

        } catch (ModelNotFoundException $e) {
            $this->error = 'Unknown deal.';
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'no artifact')) {
                $this->error = 'A deliverable needs its proof hash and live URL before it can be verified.';
            } elseif (str_contains($msg, 'live URL must return HTTP 200, got')) {
                $this->error = "The live URL must be recorded as HTTP 200; you entered {$this->httpStatus}.";
            } else {
                $this->error = $msg;
            }
        }
    }

    public function render()
    {
        $deliverables = ($this->businessId > 0)
            ? Deliverable::where('business_id', $this->businessId)->where('is_verified', true)->get()
            : collect();

        return view('x-218::deliverable-proof', [
            'deliverables' => $deliverables,
        ]);
    }
}
