<?php

declare(strict_types=1);

namespace App\Modules\X195\Ui;

use App\Modules\X195\Actions\MarketPublishAction;
use App\Modules\X195\Models\MarketItem;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Extension & Module Marketplace'])]
class MarketplaceView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $itemName = '';

    public string $itemSlug = '';

    public string $version = '';

    public string $summary = '';

    public string $success = '';

    public string $error = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function publish(MarketPublishAction $action): void
    {
        $this->success = '';
        $this->error = '';

        if (empty($this->itemName) || empty($this->itemSlug) || empty($this->version) || empty($this->summary)) {
            $this->error = 'Item name, slug, version, and summary are required.';

            return;
        }

        $item = $action->publish(
            Tenancy::idOrFail(),
            $this->itemName,
            $this->itemSlug,
            $this->version,
            ['summary' => $this->summary]
        );

        $this->success = 'Item '.$item->item_name.' ('.$item->version.') is listed. A repeat for the same slug edits the listing.';

        $this->itemName = '';
        $this->itemSlug = '';
        $this->version = '';
        $this->summary = '';
    }

    public function render()
    {
        $items = ($this->businessId > 0)
            ? MarketItem::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-195::marketplace', [
            'items' => $items,
        ]);
    }
}
