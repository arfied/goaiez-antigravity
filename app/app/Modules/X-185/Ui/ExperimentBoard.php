<?php

declare(strict_types=1);

namespace App\Modules\X185\Ui;

use App\Modules\X185\Actions\PackSeedAction;
use App\Modules\X185\Models\ContentPack;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'What is working for businesses like yours'])]
class ExperimentBoard extends Component
{
    public int $businessId = 0;

    public string $packName = '';

    public string $labelText = '';

    public int $fleetSampleSize = 150;

    public string $industry = 'hvac';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function promote(): void
    {
        $this->success = null;
        $this->error = null;

        if (trim($this->packName) === '') {
            $this->error = 'Pack Name cannot be empty.';

            return;
        }

        $bizId = Tenancy::idOrFail();

        try {
            $pack = PackSeedAction::promotePack($bizId, $this->packName, $this->labelText, $this->fleetSampleSize, $this->industry);
            $this->success = 'Promoted pack '.$pack->pack_name.'. This feeds the experiment lists; nothing downstream is wired to it yet.';
            $this->packName = '';
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $packs = ContentPack::where('business_id', $this->businessId)
            ->orderByDesc('is_promoted')
            ->orderByDesc('fleet_sample_size')
            ->get();

        return view('x-185::experiment-board', [
            'packs' => $packs,
        ]);
    }
}
