<?php

declare(strict_types=1);

namespace App\Modules\X193\Ui;

use App\Modules\X193\Actions\HoldsListAction;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Quiet-hour holds'])]
class QuiethourHolds extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $days = 7;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        $this->days = app(DefaultsRegistry::class)->int('notifications.holds.window_days');
    }

    public function window(int $days): void
    {
        if (in_array($days, [1, 7, 30], true)) {
            $this->days = $days;
        }
    }

    public function render(HoldsListAction $action)
    {
        abort_unless($this->businessId > 0, 403);

        $holds = $action->handle($this->businessId, $this->days);
        $windowStart = app(DefaultsRegistry::class)->int('notifications.quiet_hours.start');
        $windowEnd = app(DefaultsRegistry::class)->int('notifications.quiet_hours.end');

        return view('x-193::quiethour-holds', [
            'holds' => $holds,
            'windowStart' => $windowStart,
            'windowEnd' => $windowEnd,
        ]);
    }
}
