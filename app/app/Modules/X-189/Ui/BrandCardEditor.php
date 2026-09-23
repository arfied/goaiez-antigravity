<?php

declare(strict_types=1);

namespace App\Modules\X189\Ui;

use App\Modules\X189\Actions\BrandCardEnsureAction;
use App\Modules\X189\Models\BrandCard;
use App\Modules\X189\Models\BrandedMedia;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

#[Layout('components.account.layout', ['heading' => 'Brand Card'])]
class BrandCardEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $businessId = 0;

    public string $accentColor = '';

    public ?string $badgeText = '';

    public ?TemporaryUploadedFile $logo = null;

    public function mount(BrandCardEnsureAction $ensure): void
    {
        $this->businessId = Tenancy::idOrFail();
        $card = $ensure->handle($this->businessId);

        $this->accentColor = $card->accent_color;
        $this->badgeText = $card->badge_text;
    }

    public function _uploadErrored(string $name, ?string $errorsInJson, bool $isMultiple): void
    {
        $this->dispatch('upload:errored', name: $name)->self();
        $maxSize = app(DefaultsRegistry::class)->int('brand.card.max_logo_kb');
        throw ValidationException::withMessages([$name => "Upload refused. Needs to be an image under {$maxSize}KB."]);
    }

    public function save(): void
    {
        $this->validate([
            'accentColor' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badgeText' => ['nullable', 'string', 'max:'.app(DefaultsRegistry::class)->int('brand.card.badge_max_chars')],
        ]);

        $card = BrandCard::where('business_id', $this->businessId)->firstOrFail();
        $card->update([
            'accent_color' => $this->accentColor,
            'badge_text' => $this->badgeText,
        ]);

        Toaster::success('Brand card updated.');
    }

    public function uploadLogo(): void
    {
        $maxSize = app(DefaultsRegistry::class)->int('brand.card.max_logo_kb');
        $this->validate([
            'logo' => [
                'required',
                'image',
                'max:'.$maxSize,
            ],
        ]);

        $upload = $this->logo;
        if (! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        $card = BrandCard::where('business_id', $this->businessId)->firstOrFail();
        $path = $upload->storeAs('brand/'.$this->businessId, 'logo.'.$upload->getClientOriginalExtension(), 'local');

        $card->update(['logo_path' => $path]);

        $this->reset('logo');
        Toaster::success('Logo uploaded.');
    }

    public function removeLogo(): void
    {
        $card = BrandCard::where('business_id', $this->businessId)->firstOrFail();
        $card->update(['logo_path' => null]);
        Toaster::success('Logo removed.');
    }

    public function render(): View
    {
        $card = BrandCard::where('business_id', $this->businessId)->first();
        $lastMedia = BrandedMedia::where('business_id', $this->businessId)->latest('id')->first();

        return view('x-189::brand-card-editor', [
            'card' => $card,
            'lastMedia' => $lastMedia,
        ]);
    }
}
