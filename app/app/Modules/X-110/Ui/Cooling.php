<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use Carbon\CarbonInterface;
use Exception;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Cooling extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public bool $isSample = false;

    public array $dismissed = [];

    public array $openers = [];

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId;
    }

    public function dismiss(string $visitorId): void
    {
        $this->dismissed[] = $visitorId;
    }

    public function render()
    {
        try {
            $visits = Visit::where('business_id', $this->businessId)->get();
            $sessions = Session::where('business_id', $this->businessId)->get();
            $events = PixelEvent::where('business_id', $this->businessId)
                ->whereIn('event_name', ['form.abandoned', 'rage_click.detected'])
                ->get();
        } catch (Exception $e) {
            return view('x-110::cooling', ['loadError' => $e->getMessage()]);
        }

        $sessionsByVisit = $sessions->groupBy('visit_id');
        $eventsBySession = $events->groupBy('session_id');

        $visitors = [];
        $groupedVisits = $visits->groupBy('visitor_id');

        foreach ($groupedVisits as $visitorId => $visitorVisits) {
            if (in_array($visitorId, $this->dismissed)) {
                continue;
            }

            $visitCount = $visitorVisits->count();

            $visitorSessions = collect();
            foreach ($visitorVisits as $v) {
                if ($sessionsByVisit->has($v->id)) {
                    $visitorSessions = $visitorSessions->merge($sessionsByVisit->get($v->id));
                }
            }

            $latestSession = $visitorSessions->sortByDesc('started_at')->first();
            $quietTime = $latestSession ? $latestSession->started_at : null;

            $visitorEvents = collect();
            foreach ($visitorSessions as $s) {
                if ($eventsBySession->has($s->id)) {
                    $visitorEvents = $visitorEvents->merge($eventsBySession->get($s->id));
                }
            }

            $heatScore = $visitCount;
            $derivation = "{$visitCount} visits";
            $openerDerivation = 'visiting our site';

            $rageClicks = $visitorEvents->where('event_name', 'rage_click.detected')->count();
            if ($rageClicks > 0) {
                $heatScore += $rageClicks;
                $derivation .= ', experienced frustration clicking';
                $openerDerivation = 'having some trouble clicking around';
            }

            $abandoned = $visitorEvents->where('event_name', 'form.abandoned')->first();
            if ($abandoned) {
                $heatScore += 1;
                $field = $abandoned->payload['abandoned_field'] ?? 'unknown field';
                $form = $abandoned->payload['form_id'] ?? 'form';
                $derivation .= ", quit the {$form} at '{$field}'";
                $openerDerivation = "looking at our {$form} and the form didn't go through";
            }

            // §59.5: "sorted hottest-went-quietest" -> we will sort later
            $defaultOpener = "Hi — saw you were {$openerDerivation}. Want me to call you back?";

            if (! isset($this->openers[$visitorId])) {
                $this->openers[$visitorId] = $defaultOpener;
            }

            $visitors[] = [
                'visitor_id' => $visitorId,
                'heat' => $heatScore,
                'quiet_at' => $quietTime,
                'quiet_diff' => $quietTime ? $quietTime->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE]) : 'unknown',
                'derivation' => $derivation,
                'opener_text' => $this->openers[$visitorId],
            ];
        }

        // ⛔ Sort hottest-went-quietest, not by recency, per §59.5
        usort($visitors, function ($a, $b) {
            if ($a['heat'] !== $b['heat']) {
                return $b['heat'] <=> $a['heat'];
            }

            return $a['quiet_at'] <=> $b['quiet_at']; // smaller is older => quietest
        });

        return view('x-110::cooling', [
            'visitors' => $visitors,
            'loadError' => null,
        ]);
    }
}
