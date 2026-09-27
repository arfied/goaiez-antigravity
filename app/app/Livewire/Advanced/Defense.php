<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Enums\ReviewSource;
use App\Models\Review;
use App\Modules\CReviews\Domain\PublicThreshold;
use App\Modules\CReviews\Models\ReviewRemovalRequest;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Defense extends Component
{
    public function render(PublicThreshold $threshold): View
    {
        $since = now()->subDays(90);
        $min = $threshold->for((int) Tenancy::id());

        return view('livewire.advanced.defense', [
            'minPublicStars' => $min,
            'feedbackReceived' => Review::query()->where('source', ReviewSource::FirstParty)->where('created_at', '>=', $since)->count(),
            'keptPrivate' => Review::query()->where('source', ReviewSource::FirstParty)->where('created_at', '>=', $since)->where('rating', '<', $min)->count(),
            'invitedToGoogle' => Review::query()->where('source', ReviewSource::FirstParty)->where('created_at', '>=', $since)->whereNotNull('google_invite_sent_at')->count(),
            'removalRequests' => ReviewRemovalRequest::query()->where('business_id', (int) Tenancy::id())->where('created_at', '>=', $since)->count(),
        ]);
    }
}
