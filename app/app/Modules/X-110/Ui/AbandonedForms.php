<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Support\Tenancy;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Abandoned Forms'])]
class AbandonedForms extends Component
{
    #[Locked]
    public int $businessId = 0;

    public array $messages = [];

    public array $sent = [];

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recover(int $eventId)
    {
        $this->sent[$eventId] = true;
    }

    public function render()
    {
        try {
            $events = PixelEvent::where('business_id', $this->businessId)
                ->where('event_name', 'form.abandoned')
                ->latest('created_at')
                ->get();

            $sessionIds = $events->pluck('session_id')->filter()->unique();
            $sessions = Session::whereIn('id', $sessionIds)->get()->keyBy('id');

            $visitIds = $sessions->pluck('visit_id')->filter()->unique();
            $visits = Visit::whereIn('id', $visitIds)->get()->keyBy('id');

        } catch (Exception $e) {
            return view('x-110::abandoned-forms', ['loadError' => $e->getMessage()]);
        }

        $abandonments = [];
        $fieldCounts = [];

        foreach ($events as $event) {
            $rawField = $event->payload['abandoned_field'] ?? 'unknown';
            $rawForm = $event->payload['form_id'] ?? 'form';

            $field = ['phone' => 'phone number', 'email' => 'email address'][$rawField]
                ?? ucfirst(str_replace('_', ' ', (string) $rawField));

            $form = ['quote_form' => 'quote form', 'contact_form' => 'contact form'][$rawForm]
                ?? ucfirst(str_replace('_', ' ', (string) $rawForm));

            $visitorId = 'unknown';
            if ($event->session_id && isset($sessions[$event->session_id])) {
                $visitId = $sessions[$event->session_id]->visit_id;
                if ($visitId && isset($visits[$visitId])) {
                    $visitorId = $visits[$visitId]->visitor_id ?? 'unknown';
                }
            }

            if (! isset($fieldCounts[$field])) {
                $fieldCounts[$field] = 0;
            }
            $fieldCounts[$field]++;

            $defaultMsg = "Hi, saw you started filling out the {$form} but got stuck at '{$field}'. Need help?";
            if (! isset($this->messages[$event->id])) {
                $this->messages[$event->id] = $defaultMsg;
            }

            $abandonments[] = [
                'id' => $event->id,
                'visitor_id' => $visitorId,
                'form' => $form,
                'field' => $field,
                'time' => $event->created_at ? $event->created_at->diffForHumans() : 'unknown',
                'message' => $this->messages[$event->id],
                'sent' => isset($this->sent[$event->id]),
            ];
        }

        arsort($fieldCounts);
        $topKiller = count($fieldCounts) > 0 ? key($fieldCounts) : null;
        $killerCount = $topKiller ? $fieldCounts[$topKiller] : 0;

        $hasClearLeader = false;
        if (count($fieldCounts) === 1) {
            $hasClearLeader = true;
        } elseif (count($fieldCounts) > 1) {
            $counts = array_values($fieldCounts);
            if ($counts[0] > $counts[1]) {
                $hasClearLeader = true;
            }
        }

        return view('x-110::abandoned-forms', [
            'loadError' => null,
            'abandonments' => $abandonments,
            'topKiller' => $topKiller,
            'killerCount' => $killerCount,
            'hasClearLeader' => $hasClearLeader,
        ]);
    }
}
