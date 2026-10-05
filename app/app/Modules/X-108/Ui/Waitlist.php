<?php

declare(strict_types=1);

namespace App\Modules\X108\Ui;

use App\Modules\X108\Actions\AppointmentBookAction;
use App\Modules\X108\Actions\AvailabilityRequestAction;
use App\Modules\X108\Domain\SlotUnavailableRefused;
use App\Modules\X108\Models\Waitlist as WaitlistModel;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your waitlist'])]
class Waitlist extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $notice = null;

    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    /**
     * Books a waiting customer into one of the free times on the day they asked for — the same booking the calendar holds — and
     * marks their request fulfilled. The time is checked again here, never taken from the page on trust. The customer is not
     * told by the system (nothing sends on a booking), so the notice says so.
     */
    public function book(int $waitlistId, string $start, AvailabilityRequestAction $availability, AppointmentBookAction $booking, PersonLookupAction $people): void
    {
        $this->notice = null;
        $this->error = null;

        $entry = WaitlistModel::where('business_id', $this->businessId)->whereIn('status', ['pending', 'offered'])->find($waitlistId);
        if ($entry === null) {
            $this->error = 'That request is no longer waiting.';

            return;
        }
        $slot = collect($this->slotsFor($entry, $availability))->firstWhere('start_time', $start);
        if ($slot === null) {
            $this->error = 'That time is no longer free — pick another.';

            return;
        }

        try {
            DB::transaction(function () use ($entry, $slot, $booking, $people): void {
                $booking->handle(
                    $this->businessId,
                    (string) $entry->service_name,
                    $slot['start_time'],
                    $slot['end_time'],
                    (bool) $entry->is_member,
                    $people->idForPhone($this->businessId, (string) $entry->customer_phone),
                );
                $entry->update(['status' => 'fulfilled']);
            });
        } catch (SlotUnavailableRefused) {
            $this->error = 'That time was just taken — pick another.';

            return;
        }

        $this->notice = 'Booked '.$entry->customer_name.' for '.$entry->service_name.' on '.Carbon::parse($slot['start_time'])->format('D M j')
            .', '.$slot['formatted_window'].'. It is on your calendar. '.$entry->customer_name.' has not been told — call or text them.';
    }

    /**
     * The free times on the day this customer asked for, as the booking page offers them (members also get the 9 o'clock slot);
     * none once that day has passed, and none already in the past.
     *
     * @return list<array<string, mixed>>
     */
    private function slotsFor(WaitlistModel $entry, AvailabilityRequestAction $availability): array
    {
        if (! in_array($entry->status, ['pending', 'offered'], true) || $entry->preferred_date->lt(today())) {
            return [];
        }
        $slots = $availability->handle($this->businessId, $entry->preferred_date->toDateString(), (bool) $entry->is_member)['offered_slots'] ?? [];

        return array_values(array_filter($slots, static fn (array $s): bool => Carbon::parse($s['start_time'])->isFuture()));
    }

    public function render()
    {
        $availability = app(AvailabilityRequestAction::class);
        $waitlists = ($this->businessId > 0)
            ? WaitlistModel::where('business_id', $this->businessId)->orderBy('preferred_date')->orderBy('id')->get()
            : collect();

        // Not $slots: Livewire puts its own $slot and $slots into every component's view and overwrites ours.
        $times = [];
        foreach ($waitlists as $w) {
            $times[$w->id] = $this->slotsFor($w, $availability);
        }

        return view('x-108::waitlist', [
            'waitlists' => $waitlists,
            'times' => $times,
        ]);
    }
}
