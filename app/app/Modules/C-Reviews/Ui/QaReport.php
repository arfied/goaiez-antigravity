<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Domain\PublicThreshold;
use App\Modules\CReviews\Models\ReviewReply;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X181\Actions\QaTicketReadAction;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Domain\TicketAlreadyResolvedException;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'QA report'])]
class QaReport extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $days = 30;

    public ?string $drilldown = null;

    public bool $isSample = false;

    public ?string $actionNotice = null;

    public string $noticeType = 'success';

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
        $this->drilldown = null;
    }

    public function setDays(int $days): void
    {
        $this->days = $days;
        $this->drilldown = null;
    }

    public function selectDrilldown(string $key): void
    {
        $this->drilldown = $key;
    }

    public function escalateToQa(int $reviewId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        $action = app(QaTicketAction::class);
        $res = $action->handle($this->businessId, $reviewId);

        $this->noticeType = 'warning';
        $this->actionNotice = "🚨 Escalated Review #{$reviewId} to QA Ticket.";
    }

    public function resolveTicket(int $ticketId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);

        $action = app(QaTicketResolveAction::class);
        try {
            $action->handle($this->businessId, $ticketId, 'Resolved via QA Report drilldown');
        } catch (TicketAlreadyResolvedException $e) {
            $this->noticeType = 'warning';
            $this->actionNotice = $e->getMessage();

            return;
        }

        $this->noticeType = 'success';
        $this->actionNotice = "✅ Ticket #{$ticketId} resolved.";
    }

    public function render()
    {
        Tenancy::set($this->businessId);
        $since = Carbon::now()->subDays($this->days);

        $requestsSent = 0;
        $reviewsReceived = 0;
        $publicPath = 0;
        $internalQa = 0;
        $repliesPublished = 0;
        $repliesDrafted = 0;
        $openTicketsSla = 0;
        $breachedTickets = 0;

        $drilldownRows = [];

        $threshold = app(PublicThreshold::class)->for($this->businessId);

        if (! $this->isSample) {

            $internalPredicate = function ($q) use ($threshold) {
                $q->whereNotNull('rating')->where(function ($q) use ($threshold) {
                    $q->where('rating', '<', $threshold)
                        ->orWhere(function ($q) {
                            $q->where('status', 'triaged_internal');
                        });
                });
            };

            $reqsQuery = ReviewRequest::where('business_id', $this->businessId)->where('created_at', '>=', $since);
            $requestsSent = (clone $reqsQuery)->count();
            $reviewsReceived = (clone $reqsQuery)->whereNotNull('rating')->count();

            $publicPath = (clone $reqsQuery)->whereNotNull('rating')->where('rating', '>=', $threshold)->count();
            $internalQa = (clone $reqsQuery)->where($internalPredicate)->count();

            $repliesQuery = ReviewReply::where('business_id', $this->businessId)->where('created_at', '>=', $since);
            $repliesPublished = (clone $repliesQuery)->where('status', 'published')->count();
            $repliesDrafted = (clone $repliesQuery)->where('status', 'draft')->count();

            $action = app(QaTicketReadAction::class);
            $openTicketsSla = $action->countOpenTicketsSince($this->businessId, $since, false);
            $breachedTickets = $action->countOpenTicketsSince($this->businessId, $since, true);

            if ($this->drilldown === 'requests_sent') {
                $drilldownRows = (clone $reqsQuery)->get();
            }
            if ($this->drilldown === 'reviews_received') {
                $drilldownRows = (clone $reqsQuery)->whereNotNull('rating')->get();
            }
            if ($this->drilldown === 'public_path') {
                $drilldownRows = (clone $reqsQuery)->whereNotNull('rating')->where('rating', '>=', $threshold)->get();
            }
            if ($this->drilldown === 'internal_qa') {
                $drilldownRows = (clone $reqsQuery)->where($internalPredicate)->get();
            }
            if ($this->drilldown === 'replies_published') {
                $drilldownRows = (clone $repliesQuery)->where('status', 'published')->get();
            }
            if ($this->drilldown === 'replies_drafted') {
                $drilldownRows = (clone $repliesQuery)->where('status', 'draft')->get();
            }
            if ($this->drilldown === 'open_tickets_sla') {
                $drilldownRows = $action->getOpenTicketsSince($this->businessId, $since, false);
            }
            if ($this->drilldown === 'breached_tickets') {
                $drilldownRows = $action->getOpenTicketsSince($this->businessId, $since, true);
            }

            if (in_array($this->drilldown, ['requests_sent', 'reviews_received', 'public_path', 'internal_qa'])) {
                foreach ($drilldownRows as $row) {
                    $row->ticket = $action->findByReviewRequestId($this->businessId, $row->id);
                    $row->is_request = true;
                }
            }
            if (in_array($this->drilldown, ['replies_published', 'replies_drafted'])) {
                foreach ($drilldownRows as $row) {
                    $row->is_reply = true;
                }
            }
            if (in_array($this->drilldown, ['open_tickets_sla', 'breached_tickets'])) {
                foreach ($drilldownRows as $row) {
                    $row->is_ticket = true;
                }
            }
        } else {
            $requestsSent = 150;
            $reviewsReceived = 24;
            $publicPath = 20;
            $internalQa = 4;
            $repliesPublished = 20;
            $repliesDrafted = 2;
            $openTicketsSla = 3;
            $breachedTickets = 1;
        }

        $isEmpty = ! $this->isSample && $requestsSent === 0 && $reviewsReceived === 0 && $internalQa === 0 && $repliesPublished === 0 && $openTicketsSla === 0 && $breachedTickets === 0;

        return view('c-reviews::qa-report', [
            'requestsSent' => $requestsSent,
            'reviewsReceived' => $reviewsReceived,
            'publicPath' => $publicPath,
            'internalQa' => $internalQa,
            'repliesPublished' => $repliesPublished,
            'repliesDrafted' => $repliesDrafted,
            'openTicketsSla' => $openTicketsSla,
            'breachedTickets' => $breachedTickets,
            'isEmpty' => $isEmpty,
            'drilldownRows' => $drilldownRows,
            'threshold' => $threshold,
        ]);
    }
}
