<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Actions\ConfirmRemovalRequestAction;
use App\Modules\CReviews\Actions\PrepareRemovalRequestAction;
use App\Modules\CReviews\Domain\PublicThreshold;
use App\Modules\CReviews\Domain\UnauthenticatedConfirmationException;
use App\Modules\CReviews\Models\ReviewRemovalRequest;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X153\Actions\AlertSendAction;
use App\Modules\X181\Actions\QaTicketReadAction;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Domain\TicketAlreadyResolvedException;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Loss alerts'])]
class LossAlerts extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?string $actionNotice = null;

    public string $noticeType = 'success';

    public ?int $resolvingTicketId = null;

    public string $resolutionNotes = '';

    public ?int $preparingReviewId = null;

    public string $prepareTosGround = '';

    public string $prepareBody = '';

    public string $prepareGoogleId = '';

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
        $this->preparingReviewId = null;
        $this->prepareTosGround = '';
        $this->prepareBody = '';
        $this->prepareGoogleId = '';
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
            $this->noticeType = 'warning';
            $this->actionNotice = 'Sample mode, actions are off. Exit Sample to act on your own rows.';

            return;
        }
        Tenancy::set($this->businessId);
        $action = app(QaTicketResolveAction::class);
        try {
            $action->handle($this->businessId, $ticketId, $notes);
        } catch (TicketAlreadyResolvedException $e) {
            $this->noticeType = 'warning';
            $this->actionNotice = $e->getMessage().' No alert was sent.';
            $this->resolvingTicketId = null;
            $this->resolutionNotes = '';

            return;
        }

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
    }

    public function alertTeam(int $reviewRequestId): void
    {
        if ($this->isSample) {
            $this->noticeType = 'warning';
            $this->actionNotice = 'Sample mode, actions are off. Exit Sample to act on your own rows.';

            return;
        }
        Tenancy::set($this->businessId);
        $alertAction = app(AlertSendAction::class);
        $alertAction->handle(
            businessId: $this->businessId,
            title: 'Loss Alert: High Risk Customer',
            body: "Review Request #{$reviewRequestId} indicates a high risk of churn.",
            alertClass: 'account'
        );
        $this->noticeType = 'success';
        $this->actionNotice = '✅ Team alerted.';
    }

    public function startPrepare(int $id): void
    {
        $this->preparingReviewId = $id;
        $this->prepareTosGround = '';
        $this->prepareBody = '';
        $this->prepareGoogleId = '';
    }

    public function cancelPrepare(): void
    {
        $this->preparingReviewId = null;
    }

    public function prepareRemoval(int $reviewRequestId, string $tosGround, string $preparedBody, string $googleReviewId): void
    {
        if ($this->isSample) {
            $this->noticeType = 'warning';
            $this->actionNotice = 'Sample mode, actions are off. Exit Sample to act on your own rows.';

            return;
        }
        Tenancy::set($this->businessId);
        try {
            $action = app(PrepareRemovalRequestAction::class);
            $action->execute($this->businessId, $reviewRequestId, $tosGround, $preparedBody, $googleReviewId);
            $this->noticeType = 'success';
            $this->actionNotice = '✅ Removal request prepared.';
            $this->preparingReviewId = null;
        } catch (\InvalidArgumentException $e) {
            $this->noticeType = 'error';
            $this->actionNotice = '🚫 Error: '.$e->getMessage();
        }
    }

    public function confirmRemoval(int $removalId): void
    {
        if ($this->isSample) {
            $this->noticeType = 'warning';
            $this->actionNotice = 'Sample mode, actions are off. Exit Sample to act on your own rows.';

            return;
        }
        Tenancy::set($this->businessId);

        try {
            $userId = auth()->id();
            if ($userId === null) {
                throw new UnauthenticatedConfirmationException('Unauthenticated confirmation refused.');
            }

            $removal = ReviewRemovalRequest::where('business_id', $this->businessId)->findOrFail($removalId);
            $action = app(ConfirmRemovalRequestAction::class);
            $action->execute($removal, $userId);
            $this->noticeType = 'success';
            $this->actionNotice = '✅ Removal request confirmed.';
        } catch (\InvalidArgumentException|UnauthenticatedConfirmationException $e) {
            $this->noticeType = 'error';
            $this->actionNotice = '🚫 Error: '.$e->getMessage();
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $alerts = collect();
        if (! $this->isSample) {
            $minStars = app(PublicThreshold::class)->for($this->businessId);

            $breachedTickets = app(QaTicketReadAction::class)->getBreachedSlaTickets($this->businessId)
                ->map(function ($t) {
                    $t->alert_type = 'ticket';
                    $t->alert_reason = 'SLA breached ('.$t->sla_due_at->diffForHumans().')';
                    $t->risk_level = 3;

                    return $t;
                });

            $lowRatingRequests = ReviewRequest::where('business_id', $this->businessId)
                ->where('rating', '<', $minStars)
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
                    $r->alert_reason = "Rating {$r->rating} < {$minStars} and no resolved ticket";
                    $r->risk_level = 2;

                    return $r;
                });

            $lowCsatRequests = app(QaTicketReadAction::class)->getReopenedTickets($this->businessId)
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
        $removalRequests = $this->isSample ? collect() : ReviewRemovalRequest::where('business_id', $this->businessId)->get();

        return view('c-reviews::loss-alerts', [
            'alerts' => $alerts,
            'isEmpty' => $isEmpty,
            'removalRequests' => $removalRequests,
        ]);
    }
}
