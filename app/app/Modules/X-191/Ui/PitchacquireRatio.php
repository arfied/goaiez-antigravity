<?php

declare(strict_types=1);

namespace App\Modules\X191\Ui;

use App\Modules\X191\Actions\LinkPitchAction;
use App\Modules\X191\Actions\LinkProspectAction;
use App\Modules\X191\Models\LinkPitch;
use App\Modules\X191\Models\LinkPlacement;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Outreach ratio'])]
class PitchacquireRatio extends Component
{
    #[Locked]
    public int $businessId = 0;

    // Prospect form
    public string $domain = '';

    public string $targetUrl = '';

    public bool $isPbn = false;

    public int $daScore = 35;

    // Pitch form
    public string $targetId = '';

    public string $pitchBody = '';

    public string $pageSpecificFact = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function prospect(LinkProspectAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->domain) || empty($this->targetUrl)) {
            $this->error = 'Please fill out domain and target URL.';

            return;
        }

        try {
            $target = $action->prospectDomain(
                Tenancy::idOrFail(),
                $this->domain,
                $this->targetUrl,
                $this->isPbn,
                $this->daScore
            );

            $this->success = 'Recorded target '.$target->id.'. This feeds the pitch form; nothing downstream is wired to it yet.';

            $this->domain = '';
            $this->targetUrl = '';
            $this->isPbn = false;
            $this->daScore = 35;

        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function pitch(LinkPitchAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->targetId) || empty($this->pitchBody)) {
            $this->error = 'Please fill out target ID and pitch body.';

            return;
        }

        try {
            $pitch = $action->sendPitch(
                Tenancy::idOrFail(),
                (int) $this->targetId,
                $this->pitchBody,
                $this->pageSpecificFact ?: null
            );

            $this->success = 'Recorded pitch for target '.$this->targetId.'. This feeds the outreach counters; nothing downstream is wired to it yet (no email is actually sent).';

            $this->targetId = '';
            $this->pitchBody = '';
            $this->pageSpecificFact = '';

        } catch (ModelNotFoundException $e) {
            $this->error = 'Unknown target.';
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $pitchesCount = ($this->businessId > 0) ? LinkPitch::where('business_id', $this->businessId)->count() : 0;
        $earnedCount = ($this->businessId > 0) ? LinkPlacement::where('business_id', $this->businessId)->count() : 0;

        return view('x-191::pitchacquire-ratio', [
            'pitches' => $pitchesCount,
            'earned' => $earnedCount,
        ]);
    }
}
