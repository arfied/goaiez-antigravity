<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Actions\ReviewReplyAction;
use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\CReviews\Domain\PublicThreshold;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X181\Actions\QaTicketReadAction;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReviewsQaRequests extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $filter = 'all';

    public string $promptTemplate = 'How did the repair go?';

    public string $platform = 'google';

    #[Locked]
    public ?int $selectedReviewId = null;

    public string $replyDraft = '';

    public bool $isSarcasticOrAmbiguous = false;

    public ?string $actionNotice = null;

    public string $noticeType = 'success';

    public bool $isSample = false;

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
        $this->filter = 'all';
    }

    public function getThreshold(): int
    {
        Tenancy::set($this->businessId);

        return app(PublicThreshold::class)->for($this->businessId);
    }
    private function displayName(?array $person): ?string {
        if ($person === null) {
            return null;
        }
        $name = trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? ''));
        return $name !== '' ? $name : null;
    }



    public function getTicketRecipient(): string
    {
        Tenancy::set($this->businessId);
        $setting = QaSetting::where('business_id', $this->businessId)->first();
        if ($setting && ! empty($setting->ticket_recipient_id)) {
            $person = app(EntityReadAction::class)->handle('people', $setting->ticket_recipient_id, $this->businessId);

            // PB-206: people carry first_name/last_name, never name.
            return $this->displayName($person) ?? 'not set';
        }

        return 'not set';
    }

    public function sendRequest(): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);

        $lower = strtolower($this->promptTemplate);
        if (str_contains($lower, '10% off') || str_contains($lower, 'discount for review') || str_contains($lower, 'gift card')) {
            $this->noticeType = 'error';
            $this->actionNotice = '🚫 REFUSAL [INCENTIVE_GATING_BANNED]: Review incentives and review-gating discounts are strictly banned across all channels';

            return;
        }
        if (str_contains($lower, 'mention dave') || str_contains($lower, 'mention our tech') || str_contains($lower, 'mention your technician')) {
            $this->noticeType = 'error';
            $this->actionNotice = '🚫 REFUSAL [STAFF_PROMPT_BANNED]: Staff-name prompts are banned; ask "how did the repair go" instead';

            return;
        }

        $action = app(ReviewRequestAction::class);

        try {
            $res = $action->handle($this->businessId, null, $this->promptTemplate, $this->platform);
            if ($res['status'] === 'refused') {
                $this->noticeType = 'error';
                $this->actionNotice = "🚫 REFUSAL [{$res['refusal_code']}]: {$res['message']}";
            } else {
                $this->noticeType = 'success';
                $this->actionNotice = "✅ Review request dispatched via {$this->platform}.";
            }
        } catch (\Exception $e) {
            $this->noticeType = 'error';
            $this->actionNotice = '🚫 Error: '.$e->getMessage();
        }
    }

    public function resendAsk(int $id): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        $req = ReviewRequest::where('business_id', $this->businessId)->find($id);
        if (! $req) {
            return;
        }

        $action = app(ReviewRequestAction::class);
        $res = $action->handle($this->businessId, $req->customer_id, $this->promptTemplate, $req->platform, csatScore: null, jobAgeDays: (int) $req->created_at->diffInDays(now()));
        if ($res['status'] === 'refused') {
            $this->noticeType = 'error';
            $this->actionNotice = "🚫 REFUSAL [{$res['refusal_code']}]: ".($res['message'] ?? '');
        } else {
            $this->noticeType = 'success';
            $this->actionNotice = '✅ Review request resent.';
        }
    }

    public function unselectReview(): void
    {
        $this->selectedReviewId = null;
    }

    public function selectReview(int $id): void
    {
        Tenancy::set($this->businessId);
        $this->selectedReviewId = $id;
        $req = ReviewRequest::find($id);

        if (! $req) {
            return;
        }

        // Draft AI response - leave empty if no AI path
        $this->replyDraft = '';
    }

    public function publishReply(): void
    {
        if (! $this->selectedReviewId || $this->isSample) {
            return;
        }

        Tenancy::set($this->businessId);
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
            $this->actionNotice = '🛡️ P-110 SAFETY RULE: Low 1-3★ review triaged to internal QA ticket.';
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
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        $action = app(QaTicketAction::class);
        $res = $action->handle($this->businessId, $id);

        $this->noticeType = 'warning';
        $this->actionNotice = "🚨 Escalated Review #{$id} to QA Ticket [{$res['ticket_status']}].";
    }

    public function render()
    {
        Tenancy::set($this->businessId);
        $threshold = $this->getThreshold();

        $query = ReviewRequest::where('business_id', $this->businessId)->orderBy('id', 'desc');

        if ($this->filter === 'public') {
            $query->where('rating', '>=', $threshold);
        } elseif ($this->filter === 'internal') {
            $query->where('rating', '<', $threshold)->whereNotNull('rating');
        } elseif (in_array($this->filter, ['google', 'yelp', 'facebook', 'bbb'])) {
            $query->where('platform', $this->filter);
        }

        if ($this->isSample) {
            $requests = collect([
                (object) [
                    'id' => 9001,
                    'platform' => 'google',
                    'rating' => 5,
                    'status' => 'published_public',
                    'customer_name' => 'Sample Customer A',
                    'review_text' => 'Sample: quick and friendly, fixed it the same day',
                    'ticket' => null,
                ],
                (object) [
                    'id' => 9002,
                    'platform' => 'yelp',
                    'rating' => 2,
                    'status' => 'triaged_internal',
                    'customer_name' => 'Sample Customer B',
                    'review_text' => 'Sample: waited two hours past the window',
                    'ticket' => null,
                ],
            ]);
        } else {
            $requests = $query->get()->map(function ($req) {
                $req->customer_name = null;
                if ($req->customer_id) {
                    $person = app(EntityReadAction::class)->handle('people', $req->customer_id, $this->businessId);
                    if ($person) {
                        // PB-206: people carry first_name/last_name, never name.
                        $req->customer_name = $this->displayName($person);
                    }
                }
                $req->ticket = app(QaTicketReadAction::class)->findByReviewRequestId($this->businessId, $req->id);

                return $req;
            });
        }

        $totalCount = ReviewRequest::where('business_id', $this->businessId)->count();
        $publicCount = ReviewRequest::where('business_id', $this->businessId)->where('rating', '>=', $threshold)->count();
        $internalCount = ReviewRequest::where('business_id', $this->businessId)->where('rating', '<', $threshold)->whereNotNull('rating')->count();
        $rawAvg = ReviewRequest::where('business_id', $this->businessId)->whereNotNull('rating')->avg('rating');
        $avgRating = $rawAvg !== null ? round((float) $rawAvg, 1) : '—';

        $ticketRecipient = $this->getTicketRecipient();

        return view('c-reviews::reviews-qa-requests', [
            'requests' => $requests,
            'totalCount' => $totalCount,
            'publicCount' => $publicCount,
            'internalCount' => $internalCount,
            'avgRating' => $avgRating,
            'threshold' => $threshold,
            'ticketRecipient' => $ticketRecipient,
        ]);
    }
}
