<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X181\Actions\QaTicketReadAction;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Domain\TicketAlreadyResolvedException;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'QA tickets'])]
class Tickets extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $tab = 'open';

    public bool $isSample = false;

    public ?string $actionNotice = null;

    public string $noticeType = 'success';

    public ?int $resolvingTicketId = null;

    public string $resolutionNotes = '';

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $tenantId = Tenancy::id() ?: 0;
            if ($tenantId <= 0) {
                abort(403, 'Tenant context is required');
            }
            $this->businessId = (int) $tenantId;
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->tab = 'open';
        $this->resolvingTicketId = null;
        $this->resolutionNotes = '';
    }

    public function startResolve(int $id): void
    {
        $this->resolvingTicketId = $id;
        $this->resolutionNotes = '';
    }

    public function cancelResolve(): void
    {
        $this->resolvingTicketId = null;
        $this->resolutionNotes = '';
    }

    public function resolve(int $ticketId, string $notes): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);

        $action = app(QaTicketResolveAction::class);
        try {
            $action->handle($this->businessId, $ticketId, $notes);
        } catch (TicketAlreadyResolvedException $e) {
            $this->noticeType = 'warning';
            $this->actionNotice = $e->getMessage();
            $this->resolvingTicketId = null;
            $this->resolutionNotes = '';

            return;
        }

        $this->noticeType = 'success';
        $this->actionNotice = '✅ Ticket resolved successfully.';
        $this->resolvingTicketId = null;
        $this->resolutionNotes = '';
    }
    private function displayName(?array $person): ?string {
        if ($person === null) {
            return null;
        }
        $name = trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? ''));
        return $name !== '' ? $name : null;
    }



    public function render()
    {
        Tenancy::set($this->businessId);

        $tickets = collect();
        if (! $this->isSample) {
            $tickets = app(QaTicketReadAction::class)->getTicketsByTab($this->businessId, $this->tab)->map(function ($t) {
                $t->is_breached = $t->status !== 'resolved' && $t->sla_due_at && $t->sla_due_at <= now();

                $t->customer_name = 'Unknown';
                if ($t->person_id) {
                    $person = app(EntityReadAction::class)->handle('people', $t->person_id, $this->businessId);
                    if ($person) {
                        // PB-206: people carry first_name/last_name, never name.
                        $t->customer_name = $this->displayName($person) ?? 'Unknown';
                    }
                }

                $t->review_rating = null;
                $t->review_text = null;
                if ($t->review_request_id) {
                    $req = ReviewRequest::find($t->review_request_id);
                    if ($req) {
                        $t->review_rating = $req->rating;
                        $t->review_text = $req->review_text;
                    }
                }

                if ($t->status === 'resolved' && $t->arrived_at && $t->resolved_at) {
                    $t->time_to_fix = $t->arrived_at->diffForHumans($t->resolved_at, true);
                }

                return $t;
            });
        } else {
            $sampleData = collect([
                (object) [
                    'id' => 9201,
                    'status' => 'open',
                    'is_breached' => true,
                    'customer_name' => 'Sample Customer A',
                    'arrived_at' => now()->subHours(30),
                    'resolved_at' => null,
                    'sla_due_at' => now()->subHours(6),
                    'time_to_fix' => null,
                    'review_rating' => 2,
                    'review_text' => 'Sample: technician left without testing the unit',
                    'resolution_notes' => null,
                ],
                (object) [
                    'id' => 9202,
                    'status' => 'resolved',
                    'is_breached' => false,
                    'customer_name' => 'Sample Customer B',
                    'arrived_at' => now()->subHours(20),
                    'resolved_at' => now()->subHours(16),
                    'sla_due_at' => now()->addHours(4),
                    'time_to_fix' => '4 hours',
                    'review_rating' => 3,
                    'review_text' => 'Sample: fixed on the second visit',
                    'resolution_notes' => 'Sample: sent a senior technician, waived the call-out fee',
                ],
            ]);

            $tickets = $sampleData->filter(function ($t) {
                if ($this->tab === 'open') {
                    return in_array($t->status, ['open', 'in_progress']);
                } else {
                    return $t->status === 'resolved';
                }
            })->values();
        }

        $isEmpty = ! $this->isSample && app(QaTicketReadAction::class)->countTickets($this->businessId) === 0;

        return view('c-reviews::tickets', [
            'tickets' => $tickets,
            'isEmpty' => $isEmpty,
        ]);
    }
}
