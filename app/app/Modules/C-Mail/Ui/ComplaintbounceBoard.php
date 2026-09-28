<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use App\Modules\CMail\Actions\ComplaintBounceSummaryAction;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Complaint & Bounce Dashboard'])]
class ComplaintbounceBoard extends Component
{
    public int $days;

    public function mount(DefaultsRegistry $defaults): void
    {
        abort_if(Tenancy::id() === null, 403);
        $this->days = $defaults->intOr('mail.health.window_days', 30);
    }

    public function window(int $days): void
    {
        $this->days = $days;
    }

    public function render(ComplaintBounceSummaryAction $action, DefaultsRegistry $defaults)
    {
        $defaultDays = $defaults->intOr('mail.health.window_days', 30);
        $rows = $action->handle(Tenancy::idOrFail(), $this->days);

        return view('c-mail::complaintbounce-board', [
            'rows' => $rows,
            'defaultDays' => $defaultDays,
        ]);
    }
}
