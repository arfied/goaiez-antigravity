<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X153\Actions\AlertSendAction;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LossAlerts extends Component
{
    #[Locked]
    public int $businessId = 0;

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
        $this->resolvingTicketId = null;
        $this->resolutionNotes = '';
        $this->actionNotice = null;
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

    public function resolveAndAlert(int $ticketId, string $notes): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            $action = app(QaTicketResolveAction::class);
            $action->handle($this->businessId, $ticketId, $notes);

            $alertAction = app(AlertSendAction::class);
            $alertAction->handle(
                businessId: $this->businessId,
                title: 'Loss Alert: Ticket Resolved',
                body: "Ticket #{$ticketId} was resolved. Notes: {$notes}",
                alertClass: 'account'
            );

            $this->noticeType = 'success';
            $this->actionNotice = '✅ Ticket resolved and team alerted.';
            $this->resolvingTicketId = null;
            $this->resolutionNotes = '';
        } catch (\Exception $e) {
            $this->noticeType = 'error';
            $this->actionNotice = '🚫 Error: '.$e->getMessage();
        }
    }

    public function alertTeam(int $reviewRequestId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            $alertAction = app(AlertSendAction::class);
            $alertAction->handle(
                businessId: $this->businessId,
                title: 'Loss Alert: High Risk Customer',
                body: "Review Request #{$reviewRequestId} indicates a high risk of churn.",
                alertClass: 'account'
            );
            $this->noticeType = 'success';
            $this->actionNotice = '✅ Team alerted.';
        } catch (\Exception $e) {
            $this->noticeType = 'error';
            $this->actionNotice = '🚫 Error: '.$e->getMessage();
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $alerts = collect();
        if (! $this->isSample) {
            $settings = QaSetting::where('business_id', $this->businessId)->first();
            $minStars = $settings ? $settings->min_public_stars : 4;

            $breachedTickets = \Illuminate\Support\Facades\DB::table('qa_tickets')->where('business_id', $this->businessId)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<=', now())
                ->orderBy('sla_due_at', 'asc')
                ->get()
                ->map(function ($t) {
                    $t->alert_type = 'ticket';
                    $t->alert_reason = 'SLA breached ('.$t->sla_due_at->diffForHumans().')';
                    $t->risk_level = 3;

                    return $t;
                });

            $lowRatingRequests = ReviewRequest::where('business_id', $this->businessId)
                ->where('rating', '<=', $minStars)
                ->whereNotNull('rating')
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('qa_tickets')
                        ->whereColumn('qa_tickets.review_request_id', 'review_requests.id')
                        ->where('qa_tickets.status', 'resolved');
                })
                ->get()
                ->map(function ($r) use ($minStars) {
                    $r->alert_type = 'review';
                    $r->alert_reason = "Rating {$r->rating} <= {$minStars} and no resolved ticket";
                    $r->risk_level = 2;

                    return $r;
                });

            $lowCsatRequests = \Illuminate\Support\Facades\DB::table('qa_tickets')->where('business_id', $this->businessId)
                ->whereNotNull('reopened_at')
                ->get()
                ->map(function ($t) {
                    $t->alert_type = 'ticket';
                    $t->alert_reason = 'Resolved ticket was reopened due to low CSAT';
                    $t->risk_level = 1;

                    return $t;
                });

            $alerts = $breachedTickets->concat($lowRatingRequests)->concat($lowCsatRequests)
                ->sortByDesc('risk_level')
                ->unique(function ($item) {
                    return $item->alert_type.'-'.$item->id;
                })
                ->values();
        } else {
            $alerts = collect([
                (object) [
                    'id' => 999,
                    'alert_type' => 'ticket',
                    'alert_reason' => 'SLA breached (2 hours ago)',
                    'risk_level' => 3,
                ],
            ]);
        }

        $isEmpty = ! $this->isSample && $alerts->isEmpty();

        return view('c-reviews::loss-alerts', [
            'alerts' => $alerts,
            'isEmpty' => $isEmpty,
        ]);
    }
}
