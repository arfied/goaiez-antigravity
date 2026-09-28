<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\ReviewSource;
use App\Exceptions\GbpRequestFailed;
use App\Models\Review;
use App\Modules\X182\Actions\SocialAccountLookupAction;
use App\Services\Reviews\FacebookReviewIngest;
use App\Services\Zernio\ZernioSocialClient;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

#[Layout('components.account.layout')]
final class FacebookReviews extends Component
{
    public array $replyText = [];

    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);
    }

    public function reply(int $reviewId, ZernioSocialClient $client): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $review = Review::query()
            ->where('source', ReviewSource::Facebook)
            ->find($reviewId);

        if ($review === null) {
            Toaster::error('That review is not here any more.');

            return;
        }

        if ($review->owner_replied_at !== null) {
            Toaster::info('You already replied to this review.');

            return;
        }

        if ($review->provider_review_id === null) {
            Toaster::error('This review cannot be answered from here.');

            return;
        }

        $ref = app(SocialAccountLookupAction::class)->facebookAccountRefForLocation((int) $review->location_id);
        if ($ref === null) {
            Toaster::error("Connect this location's Facebook Page first.");

            return;
        }

        $text = trim((string) ($this->replyText[$reviewId] ?? ''));
        if ($text === '' || mb_strlen($text) > 1500) {
            Toaster::error('Write a reply of up to 1,500 characters.');

            return;
        }

        try {
            $replyRef = $client->replyToFacebookReview($ref, $review->provider_review_id, $text, 'fb-review-reply-'.$review->id);
        } catch (GbpRequestFailed $e) {
            Toaster::error('Facebook did not accept the reply: '.$e->getMessage());

            return;
        }

        app(FacebookReviewIngest::class)->recordOwnerReply($review, $text, $replyRef);
        Toaster::success('Replied on Facebook.');
        unset($this->replyText[$reviewId]);
    }

    public function render(): View
    {
        abort_if(Tenancy::id() === null, 403);

        $reviews = Review::query()
            ->where('source', ReviewSource::Facebook)
            ->latest('review_create_time')
            ->limit(100)
            ->get();

        return view('livewire.account.facebook-reviews', [
            'reviews' => $reviews,
        ]);
    }
}
