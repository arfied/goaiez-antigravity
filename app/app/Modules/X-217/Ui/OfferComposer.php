<?php

declare(strict_types=1);

namespace App\Modules\X217\Ui;

use App\Modules\X217\Actions\AffiliateTermsOfferAction;
use App\Modules\X217\Models\RecruitmentOffer;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OfferComposer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $prospectId = '';

    public string $offeredRateBps = '';

    public string $success = '';

    public string $error = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function makeOffer(AffiliateTermsOfferAction $action): void
    {
        $this->success = '';
        $this->error = '';

        if (empty($this->prospectId) || empty($this->offeredRateBps)) {
            $this->error = 'Prospect ID and offered rate are required.';

            return;
        }

        try {
            $offer = $action->makeOffer(
                Tenancy::idOrFail(),
                (int) $this->prospectId,
                (int) $this->offeredRateBps
            );

            $this->success = 'Made terms offer to prospect '.$offer->prospect_id
                .'. This feeds the pipeline; nothing downstream is wired to it yet.';

            $this->prospectId = '';
            $this->offeredRateBps = '';
        } catch (ModelNotFoundException $e) {
            $this->error = 'That prospect was not found in your pipeline.';
        } catch (\InvalidArgumentException $e) {
            // Map "(TEST ANCHOR)" out of the message
            $this->error = 'That rate is above the 2500 bps ceiling, so no offer was made.';
        }
    }

    public function render()
    {
        $offers = ($this->businessId > 0)
            ? RecruitmentOffer::where('business_id', $this->businessId)->with('prospect')->get()
            : collect();

        return view('x-217::offer-composer', [
            'offers' => $offers,
        ]);
    }
}
