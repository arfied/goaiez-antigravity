<?php

declare(strict_types=1);

namespace App\Modules\X217\Ui;

use App\Modules\X217\Actions\AffiliatePipelineAction;
use App\Modules\X217\Actions\AffiliateTermsOfferAction;
use App\Modules\X217\Domain\RecruitmentGuard;
use App\Modules\X217\Models\RecruitmentOffer;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Offer Composer'])]
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
            $this->error = 'That rate is above the '.RecruitmentGuard::DEFAULT_MAX_CEILING_BPS.' bps ceiling, so no offer was made.';
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

    public function acceptOffer(AffiliatePipelineAction $action, int $offerId): void
    {
        $this->success = '';
        $this->error = '';

        $offer = RecruitmentOffer::where('business_id', Tenancy::idOrFail())->findOrFail($offerId);

        if ($offer->is_accepted) {
            $this->error = 'That offer was already accepted, so nothing was done. Accepting it twice would create a second affiliate for the same partner.';

            return;
        }

        $partnerName = $offer->prospect->partner_name ?? 'The partner';
        $rateBps = $offer->offered_rate_bps;

        $code = $action->acceptOffer(Tenancy::idOrFail(), $offer->id);

        $this->success = 'Accepted. '.$partnerName.' is now an affiliate under code '.$code
            .' at '.$rateBps.' bps — the exact rate that was offered. Their balance is 0 cents and '
            .'nothing has been paid yet. Nobody is notified, so send them the code yourself.';
    }
}
