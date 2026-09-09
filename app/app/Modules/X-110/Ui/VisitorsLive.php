<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Real-time Visitors'])]
class VisitorsLive extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public ?string $openVisitor = null;

    public function openEvents(string $visitorId): void
    {
        if ($this->openVisitor === $visitorId) {
            $this->openVisitor = null;
        } else {
            $this->openVisitor = $visitorId;
        }
    }

    public function render()
    {
        try {
            $sessions = Session::where('visitor_sessions.business_id', $this->businessId)
                ->where('visitor_sessions.started_at', '>=', now()->subMinutes(30))
                ->join('visits', 'visits.id', '=', 'visitor_sessions.visit_id')
                ->orderByDesc('visitor_sessions.started_at')
                ->select('visitor_sessions.*', 'visits.visitor_id', 'visits.landing_page', 'visits.utm_source', 'visits.utm_medium', 'visits.utm_campaign')
                ->get();

            $installVerified = PixelEvent::where('business_id', $this->businessId)->exists();

            $openVisitorEvents = null;
            if ($this->openVisitor) {
                $openVisitorEvents = PixelEvent::where('pixel_events.business_id', $this->businessId)
                    ->join('visitor_sessions', 'visitor_sessions.id', '=', 'pixel_events.session_id')
                    ->join('visits', 'visits.id', '=', 'visitor_sessions.visit_id')
                    ->where('visits.visitor_id', $this->openVisitor)
                    ->orderByDesc('pixel_events.created_at')
                    ->limit(5)
                    ->select('pixel_events.event_name', 'pixel_events.created_at')
                    ->get()
                    ->toBase()
                    ->map(fn (PixelEvent $e) => ['label' => self::eventLabel((string) $e->event_name), 'at' => $e->created_at ? $e->created_at->diffForHumans() : 'unknown']);
            }
        } catch (\Exception $e) {
            return view('x-110::visitors-live', ['loadError' => $e->getMessage()]);
        }

        return view('x-110::visitors-live', [
            'sessions' => $sessions,
            'installVerified' => $installVerified,
            'openVisitorEvents' => $openVisitorEvents,
            'loadError' => null,
        ]);
    }

    private static function eventLabel(string $raw): string
    {
        return [
            'pageview' => 'Viewed a page',
            'page_view' => 'Viewed a page',
            'page.viewed' => 'Viewed a page',
            'form.abandoned' => 'Left a form unfinished',
            'rage_click.detected' => 'Clicked the same thing repeatedly',
            'tag.fired' => 'Tag fired',
            'custom_event' => 'Custom event',
        ][$raw] ?? ucfirst(str_replace(['.', '_'], ' ', $raw));
    }
}
