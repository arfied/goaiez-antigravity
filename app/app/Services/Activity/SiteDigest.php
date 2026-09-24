<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Modules\X102\Models\ChatLead;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Models\SiteRecommendation;
use App\Modules\X108\Models\Waitlist;
use App\Modules\X155\Models\FormSubmission;
use Carbon\CarbonInterface;

/**
 * The "what your website did" lines of the weekly owner digest.
 *
 * Every line is prefixed "Your website: " so the reader can tell the site's
 * numbers from the autopilot's. A zero is never a line. Runs under
 * Tenancy::actingAs() like the rest of the digest, so every query is already
 * scoped by RLS; the explicit business_id is belt and braces, not the scope.
 *
 * @return list<string>
 */
final class SiteDigest
{
    public function lines(int $businessId, CarbonInterface $since): array
    {
        $lines = [];

        $published = PageVersion::query()->where('business_id', $businessId)->where('created_at', '>=', $since)->count();
        if ($published > 0) {
            $lines[] = 'Your website: '.$published.' '.($published === 1 ? 'page' : 'pages').' published';
        }

        $forms = FormSubmission::query()->where('business_id', $businessId)->where('is_spam', false)->where('created_at', '>=', $since)->count();
        if ($forms > 0) {
            $lines[] = 'Your website: '.$forms.' form '.($forms === 1 ? 'lead' : 'leads');
        }

        $bookings = Waitlist::query()->where('business_id', $businessId)->where('created_at', '>=', $since)->count();
        if ($bookings > 0) {
            $lines[] = 'Your website: '.$bookings.' booking '.($bookings === 1 ? 'request' : 'requests');
        }

        $chats = ChatLead::query()->where('business_id', $businessId)->where('created_at', '>=', $since)->count();
        if ($chats > 0) {
            $lines[] = 'Your website: '.$chats.' chat '.($chats === 1 ? 'lead' : 'leads');
        }

        $waiting = SiteRecommendation::query()->where('business_id', $businessId)->where('status', 'pending')->count();
        if ($waiting > 0) {
            $lines[] = 'Your website: '.$waiting.' '.($waiting === 1 ? 'suggestion' : 'suggestions').' waiting for you';
        }

        return $lines;
    }
}
