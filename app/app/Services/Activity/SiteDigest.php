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
    public function lines(int $businessId, CarbonInterface $since, ?CarbonInterface $until = null): array
    {
        $lines = [];

        $published = PageVersion::query()->where('business_id', $businessId)->where('created_at', '>=', $since)->when($until !== null, fn ($q) => $q->where('created_at', '<', $until))->count();
        if ($published > 0) {
            $lines[] = 'Your website: '.$published.' '.($published === 1 ? 'page' : 'pages').' published';
        }

        $forms = FormSubmission::query()->where('business_id', $businessId)->where('is_spam', false)->where('created_at', '>=', $since)->when($until !== null, fn ($q) => $q->where('created_at', '<', $until))->count();
        if ($forms > 0) {
            $lines[] = 'Your website: '.$forms.' form '.($forms === 1 ? 'lead' : 'leads');
        }

        $bookings = Waitlist::query()->where('business_id', $businessId)->where('created_at', '>=', $since)->when($until !== null, fn ($q) => $q->where('created_at', '<', $until))->count();
        if ($bookings > 0) {
            $lines[] = 'Your website: '.$bookings.' booking '.($bookings === 1 ? 'request' : 'requests');
        }

        $chats = ChatLead::query()->where('business_id', $businessId)->where('created_at', '>=', $since)->when($until !== null, fn ($q) => $q->where('created_at', '<', $until))->count();
        if ($chats > 0) {
            $lines[] = 'Your website: '.$chats.' chat '.($chats === 1 ? 'lead' : 'leads');
        }

        $waiting = SiteRecommendation::query()->where('business_id', $businessId)->where('status', 'pending')->count();
        if ($waiting > 0) {
            $lines[] = 'Your website: '.$waiting.' '.($waiting === 1 ? 'suggestion' : 'suggestions').' waiting for you';
        }

        return $lines;
    }

    /** Pages published in the window, by title, newest first: list of ['title' => string, 'times' => int]. */
    public function pagesPublished(int $businessId, CarbonInterface $since, ?CarbonInterface $until = null): array
    {
        return PageVersion::query()
            ->join('pages', 'pages.id', '=', 'page_versions.page_id')
            ->where('page_versions.business_id', $businessId)
            ->where('page_versions.created_at', '>=', $since)
            ->when($until !== null, fn ($q) => $q->where('page_versions.created_at', '<', $until))
            ->selectRaw('pages.title as title, count(*) as times, max(page_versions.created_at) as last_at')
            ->groupBy('pages.title')
            ->orderByDesc('last_at')
            ->get()
            ->map(fn ($row): array => ['title' => (string) $row->title, 'times' => (int) $row->times])
            ->all();
    }
}
