<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Actions\ReviewReplyAction;
use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\CReviews\Actions\ReviewSyncAction;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReviewsQaRequests extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $filter = 'all';

    public string $promptTemplate = 'How did the repair go? We would love your feedback.';

    public string $platform = 'google';

    public ?int $selectedReviewId = null;

    public string $replyDraft = '';

    public string $replyTone = 'professional';

    public bool $isSarcasticOrAmbiguous = false;

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
        DB::statement("SET app.business_id = '{$this->businessId}'");

        // Seed initial sample reviews if empty
        if (ReviewRequest::where('business_id', $this->businessId)->count() === 0) {
            $sync = app(ReviewSyncAction::class);
            $sync->handle($this->businessId, 'google', 5, 'Exceptional emergency leak repair on Sunday afternoon! Tech arrived in 25 mins.');
            $sync->handle($this->businessId, 'google', 5, 'Ava answered my call immediately and booked technician John. 5-star service.');
            $sync->handle($this->businessId, 'yelp', 2, 'The plumber fixed the issue, but arrived 45 minutes past the 2-hour dispatch window.');
            $sync->handle($this->businessId, 'google', 1, 'Water heater pressure valve leaked again 2 days after installation.');
        }
    }

    public function sendRequest(): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $action = app(ReviewRequestAction::class);
        $res = $action->handle($this->businessId, null, $this->promptTemplate, $this->platform);

        if ($res['status'] === 'refused') {
            $this->noticeType = 'error';
            $this->actionNotice = "🚫 REFUSAL [{$res['refusal_code']}]: {$res['message']}";
        } else {
            $this->noticeType = 'success';
            $this->actionNotice = "✅ Review request dispatched via {$this->platform} (P-110 compliant, zero-incentive rule).";
        }
    }

    public function selectReview(int $id): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $this->selectedReviewId = $id;
        $req = ReviewRequest::find($id);

        if (! $req) {
            return;
        }

        if ($req->rating >= 4) {
            $this->replyDraft = 'Thank you so much for your kind words! We take great pride in our fast dispatch and transparent service. We look forward to helping you again!';
        } else {
            $this->replyDraft = 'We sincerely apologize for falling short of your expectations. Our operations manager is investigating your job and will reach out directly.';
        }
    }

    public function publishReply(): void
    {
        if (! $this->selectedReviewId) {
            return;
        }

        DB::statement("SET app.business_id = '{$this->businessId}'");
        $action = app(ReviewReplyAction::class);
        $res = $action->handle(
            $this->businessId,
            $this->selectedReviewId,
            $this->replyDraft,
            $this->isSarcasticOrAmbiguous
        );

        if ($res['status'] === 'refused') {
            $this->noticeType = 'error';
            $this->actionNotice = "🚫 REFUSAL [{$res['refusal_code']}]: {$res['message']}";
        } elseif ($res['status'] === 'triaged_internal') {
            $this->noticeType = 'warning';
            $this->actionNotice = '🛡️ P-110 SAFETY RULE: Low 1-3★ review triaged to internal QA ticket. No public reply published to prevent review flame-wars.';
        } elseif ($res['status'] === 'draft') {
            $this->noticeType = 'warning';
            $this->actionNotice = '📝 Sarcasm/Ambiguity detected: Reply saved as draft to inbox for human verification.';
        } else {
            $this->noticeType = 'success';
            $this->actionNotice = '🎉 5-Star response published publicly! First-Win event dispatched.';
        }

        $this->selectedReviewId = null;
        $this->replyDraft = '';
    }

    public function escalateToQa(int $id): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $action = app(QaTicketAction::class);
        $res = $action->handle($this->businessId, $id);

        $this->noticeType = 'warning';
        $this->actionNotice = "🚨 Escalated Review #{$id} to QA Ticket with Status [{$res['ticket_status']}]. CSAT follow-up scheduled on resolve.";
    }

    public function addSampleReview(string $platform, int $rating, string $text): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $sync = app(ReviewSyncAction::class);
        $sync->handle($this->businessId, $platform, $rating, $text);

        $this->noticeType = 'success';
        $this->actionNotice = "📥 Ingested new {$rating}★ review from {$platform}.";
    }

    public function render()
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");

        $query = ReviewRequest::where('business_id', $this->businessId)->orderBy('id', 'desc');

        if ($this->filter === '5star') {
            $query->where('rating', '>=', 4);
        } elseif ($this->filter === '1to3star') {
            $query->where('rating', '<=', 3);
        } elseif ($this->filter === 'google') {
            $query->where('platform', 'google');
        } elseif ($this->filter === 'yelp') {
            $query->where('platform', 'yelp');
        }

        $requests = $query->get();
        $totalCount = ReviewRequest::where('business_id', $this->businessId)->count();
        $fiveStarCount = ReviewRequest::where('business_id', $this->businessId)->where('rating', 5)->count();
        $qaCount = ReviewRequest::where('business_id', $this->businessId)->where('status', 'triaged_internal')->count();
        $rawAvg = ReviewRequest::where('business_id', $this->businessId)->whereNotNull('rating')->avg('rating');
        $avgRating = $rawAvg !== null ? (float) $rawAvg : 5.0;

        return view('c-reviews::reviews-qa-requests', [
            'requests' => $requests,
            'totalCount' => $totalCount,
            'fiveStarCount' => $fiveStarCount,
            'qaCount' => $qaCount,
            'avgRating' => round($avgRating, 1),
        ]);
    }
}
