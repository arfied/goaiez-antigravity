<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\ReviewSource;
use App\Models\Review;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout')]
final class FacebookReviews extends Component
{
    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);
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
