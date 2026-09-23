<?php

declare(strict_types=1);

namespace App\Modules\X189\Ui;

use App\Modules\X189\Actions\ImageOverlayAction;
use App\Modules\X189\Models\BrandedMedia;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Branded media'])]
class PreviewPerDestination extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $sourceAssetUrl = null;

    public ?string $licenseSource = null;

    public string $destination = 'social';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function brandAsset(ImageOverlayAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->sourceAssetUrl) || empty($this->destination)) {
            $this->error = 'Please fill out required fields.';

            return;
        }

        $result = $action->overlay(
            Tenancy::idOrFail(),
            $this->sourceAssetUrl,
            $this->licenseSource,
            $this->destination
        );

        if (($result['status'] ?? null) === 'refused') {
            $this->error = str_replace(' (§226)', '', $result['message'] ?? 'Refused');

            return;
        }

        $this->success = 'Recorded branding for asset; no image was actually produced.';

        $this->sourceAssetUrl = null;
        $this->licenseSource = null;
        $this->destination = 'social';
    }

    public function render()
    {
        $media = BrandedMedia::where('business_id', $this->businessId)->orderByDesc('id')->get();

        return view('x-189::preview-per-destination', [
            'media' => $media,
        ]);
    }
}
