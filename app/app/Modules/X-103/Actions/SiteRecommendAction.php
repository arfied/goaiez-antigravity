<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Location;
use App\Models\Review;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteRecommendation;
use App\Modules\X110\Actions\PixelEventsAction;
use App\Services\Config\DefaultsRegistry;

class SiteRecommendAction
{
    public function __construct(
        private readonly PixelEventsAction $pixelEventsAction,
        private readonly DefaultsRegistry $registry
    ) {}

    public function handle(int $businessId): array
    {
        $pages = Page::where('business_id', $businessId)->get();
        $locationId = Location::where('business_id', $businessId)->orderBy('id')->value('id');

        $recommendations = [];

        // 1. form_abandoned
        $recentAbandonments = $this->pixelEventsAction->abandonedFormsSince($businessId, now()->subDays(7));
        $n = count($recentAbandonments);
        if ($n >= 3) {
            $fields = array_column($recentAbandonments, 'abandoned_field');
            $counts = array_count_values(array_filter($fields));
            if ($counts) {
                arsort($counts);
                $mostCommonField = array_key_first($counts);
                $text = "{$n} visitors gave up on your form this week, most at the '{$mostCommonField}' field. Consider making that field optional or moving it last.";
                $recommendations['form_abandoned'] = $text;
            }
        }

        // 2. reviews_stale
        if ($locationId) {
            $minRating = $this->registry->int('sites.draft.reviews_min_rating');
            // displayable() is the moderation gate — the same one the public widget feed applies; display fails closed.
            $available = Review::query()->displayable()->where('location_id', $locationId)
                ->where('display_on_website', true)
                ->where('rating', '>=', $minRating)
                ->count();

            $shown = 0;
            foreach ($pages as $page) {
                $blocks = $page->draft_blocks ?? [];
                foreach ($blocks as $block) {
                    if (($block['type'] ?? '') === 'reviews_strip') {
                        $count = count($block['items'] ?? []);
                        if ($count > $shown) {
                            $shown = $count;
                        }
                    }
                }
            }

            if ($available > $shown && $available >= 3) {
                $recommendations['reviews_stale'] = "You have {$available} reviews that could show on your site; it shows {$shown}. Rebuild the reviews strip to add the newest.";
            }
        }

        // 3. hours_missing
        $hoursMissing = false;
        foreach ($pages as $page) {
            $blocks = $page->draft_blocks ?? [];
            foreach ($blocks as $block) {
                if (($block['type'] ?? '') === 'contact' && ! array_key_exists('hours', $block)) {
                    $hoursMissing = true;
                    break 2;
                }
            }
        }

        if ($hoursMissing) {
            $recommendations['hours_missing'] = 'Your contact section has no opening hours. Tell the site your hours and it will show them.';
        }

        $firedCodes = array_keys($recommendations);

        $query = SiteRecommendation::where('business_id', $businessId);
        if ($firedCodes) {
            $query->whereNotIn('code', $firedCodes);
        }
        $query->delete();

        $wrote = [];
        foreach ($recommendations as $code => $text) {
            $row = SiteRecommendation::firstOrNew([
                'business_id' => $businessId,
                'code' => $code,
            ]);

            if (! $row->exists) {
                $row->status = 'pending';
            }

            $row->text = $text;
            $row->computed_at = now();
            $row->save();
            $wrote[] = $row;
        }

        return $wrote;
    }
}
