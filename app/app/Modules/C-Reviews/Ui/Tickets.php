<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\X181\Models\QaTicket;
use App\Modules\X121\Models\Person;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

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
        $this->isSample = !$this->isSample;
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
        if ($this->isSample) return;
        Tenancy::set($this->businessId);
        
        try {
            $action = app(\App\Modules\X181\Actions\QaTicketResolveAction::class);
            $action->handle($this->businessId, $ticketId, $notes);
            
            $this->noticeType = 'success';
            $this->actionNotice = "✅ Ticket resolved successfully.";
            $this->resolvingTicketId = null;
            $this->resolutionNotes = '';
        } catch (\Exception $e) {
            $this->noticeType = 'error';
            $this->actionNotice = "🚫 Error: " . $e->getMessage();
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);
        
        $tickets = collect();
        if (!$this->isSample) {
            $query = QaTicket::where('business_id', $this->businessId);
            
            if ($this->tab === 'open') {
                $query->whereIn('status', ['open', 'in_progress'])->orderBy('sla_due_at', 'asc');
            } else {
                $query->where('status', 'resolved')->orderBy('resolved_at', 'desc');
            }
            
            $tickets = $query->get()->map(function($t) {
                $t->is_breached = $t->status !== 'resolved' && $t->sla_due_at && $t->sla_due_at <= now();
                
                $t->customer_name = 'Unknown';
                if ($t->person_id) {
                    $person = Person::find($t->person_id);
                    if ($person) $t->customer_name = $person->name;
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
            // For sample mode we can leave it empty, as the blade will show sample data manually or just show sample message
        }
        
        $isEmpty = !$this->isSample && QaTicket::where('business_id', $this->businessId)->count() === 0;
        
        return view('c-reviews::tickets', [
            'tickets' => $tickets,
            'isEmpty' => $isEmpty,
        ]);
    }
}
