<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Modules\X110\Actions\PixelEventsAction;
use App\Modules\X110\Domain\PixelEngine;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Visit;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Worth a minute'])]
class Today extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public ?string $loadError = null;

    public string $visitorId = '';

    public string $error = '';

    public string $success = '';

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recordTestVisit(PixelEngine $engine, PixelEventsAction $events): void
    {
        if (trim($this->visitorId) === '') {
            $this->error = 'Visitor ID is required.';

            return;
        }

        $result = $engine->recordVisit(Tenancy::idOrFail(), $this->visitorId);
        $events->handle(Tenancy::idOrFail(), (int) $result['session_id'], 'page_view', []);

        $this->success = 'Recorded visit for visitor '.$this->visitorId.'. This feeds the live visitor list; nothing downstream is wired to it yet.';
        $this->error = '';
        $this->visitorId = '';
    }

    public function render()
    {
        if ($this->businessId === 0) {
            return view('x-110::today', [
                'isVerified' => false,
                'todayVisitsCount' => 0,
            ]);
        }

        $isVerified = PixelEvent::where('business_id', $this->businessId)->exists();

        $todayVisitsCount = Visit::where('business_id', $this->businessId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return view('x-110::today', [
            'isVerified' => $isVerified,
            'todayVisitsCount' => $todayVisitsCount,
        ]);
    }
}
