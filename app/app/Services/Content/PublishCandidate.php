<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\GrowthPageStatus;
use App\Enums\GrowthPageType;
use Illuminate\Support\Carbon;

/**
 * One `growth_pages` row, as everything outside {@see GrowthPages} may see it.
 *
 * ⛔ **THE PIPELINE DEALS IN THIS AND NEVER IN THE MODEL, AND THAT IS THE
 * CHOKEPOINT RATHER THAN A STYLE** (5661). `GrowthPages` is the only file in
 * `app/` permitted to name `App\Models\GrowthPage` — a lint in
 * `Architecture\ContentTest` fails the build on the import — because `status`
 * and `hold_until` together decide whether text this platform wrote appears on
 * somebody else's website. {@see ContentQuality::assess()} already answers the
 * same constraint by taking an `int $pageId`; this is that pattern with the
 * fields the publisher actually needs attached.
 *
 * ⚠️ **IT IS A COPY AND GOES STALE.** Nothing here writes anything back, and a
 * candidate read before a hold was opened still says the hold is absent. Every
 * writer takes the id and re-reads under the tenant scope.
 */
final readonly class PublishCandidate
{
    public function __construct(
        public int $id,
        public int $locationId,
        public GrowthPageType $type,
        public string $slug,
        public PageCopy $copy,
        public ?string $targetKeyword,
        public GrowthPageStatus $status,
        public ?Carbon $holdUntil,
        public ?Carbon $publishedAt,
    ) {}

    /**
     * Whether this hold lapses on its own, or waits for a person (5565).
     */
    public function releasesOnSilence(): bool
    {
        return $this->status === GrowthPageStatus::Held && $this->holdUntil !== null;
    }

    public function isPublished(): bool
    {
        return $this->status === GrowthPageStatus::Published;
    }

    /**
     * The address this page would live at on the tenant's own site.
     *
     * ⚠️ **BUILT FROM THE CONFIRMED ADDRESS, NEVER FROM A HOST WE DERIVED**
     * (1083's franchisor rule, 5540). `LocationWebsite::normalise()` has already
     * stripped the trailing slash and kept any path the owner confirmed, so the
     * join is one slash and no guessing.
     */
    public function urlOn(string $websiteUrl): string
    {
        return rtrim($websiteUrl, '/').'/'.ltrim($this->slug, '/');
    }
}
