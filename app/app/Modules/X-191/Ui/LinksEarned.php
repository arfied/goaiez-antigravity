<?php

declare(strict_types=1);

namespace App\Modules\X191\Ui;

use App\Modules\X191\Actions\LinkMonitorAction;
use App\Modules\X191\Models\LinkPlacement;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Earned links'])]
class LinksEarned extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $placedUrl = '';

    public string $anchorText = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recordPlacement(): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->placedUrl)) {
            $this->error = 'Placed URL is required.';

            return;
        }

        $result = app(LinkMonitorAction::class)->recordPlacement(
            Tenancy::idOrFail(),
            $this->placedUrl,
            $this->anchorText
        );

        $this->success = 'Recorded placement on '.$result->placed_url.'. This feeds the pitch ratios; nothing downstream is wired to it yet.';
        $this->placedUrl = '';
        $this->anchorText = '';
    }

    public function render()
    {
        $placements = ($this->businessId > 0)
            ? LinkPlacement::where('business_id', $this->businessId)->where('is_active', true)->orderByDesc('id')->get()
            : collect();

        return view('x-191::links-earned', [
            'placements' => $placements,
        ]);
    }
}
