<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Models\PixelBundleVersion;
use App\Services\Config\DefaultsRegistry;
use App\Services\Pixel\PixelDelivery;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Tag Versions'])]
class TagVersionPer extends Component
{
    public function render(PixelDelivery $delivery)
    {
        return view('x-110::tag-version-per', [
            'versions' => PixelBundleVersion::query()->orderByDesc('published_at')->orderByDesc('id')->limit(20)->get(),
            'active' => $delivery->currentActive(),
            'canary' => $delivery->currentCanary(),
            'canaryBp' => app(DefaultsRegistry::class)->int('pixel.canary_percent_bp'),
        ]);
    }
}
