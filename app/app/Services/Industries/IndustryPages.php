<?php

declare(strict_types=1);

namespace App\Services\Industries;

use App\Enums\IndustryFamily;
use App\Models\IndustryPage;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Collection;

/**
 * Reads of the hundred industry pages — CC-3 §3 and §4, PIII-72 §A2's interlink
 * law.
 *
 * The one place that answers *which pages exist, in what order, and which of them
 * link to each other*. The controller renders; the sitemap generator lists; both
 * ask here, so the hub's grouping and the sitemap's inclusion rule cannot drift
 * apart into two readings of one column.
 */
final readonly class IndustryPages
{
    /**
     * PIII-72 §A2.2: *"page → 4–6 family siblings"*.
     */
    public const int MAX_SIBLINGS = 6;

    public function __construct(private DefaultsRegistry $defaults) {}

    /**
     * Whether the engine is switched on at all.
     *
     * ⚠️ **THE KEY NAME IS A CROSS-LANE CONTRACT.** CC-2's HUNDRED-KINDS block
     * and its `/industries` hub shell bind to this literal string, so the two
     * slices switch on together or not at all. Seeded `false`: the pages exist,
     * nothing routes to them, and the flip is one registry edit.
     */
    public function enabled(): bool
    {
        return $this->defaults->value('features.industry_pages') === true;
    }

    /**
     * Every page, grouped into the hub's six sections in `IndustryFamily`'s
     * declaration order, each section in `position` order.
     *
     * ⚠️ **A FAMILY WITH NO ROWS IS ABSENT RATHER THAN EMPTY.** `medspa` has
     * exactly one row in the authored hundred and a seventh family could have
     * none; a heading over nothing is a dead section on a public page.
     *
     * @return array<string, Collection<int, IndustryPage>>
     */
    public function hub(): array
    {
        $byFamily = IndustryPage::query()
            ->orderBy('position')
            ->get()
            ->groupBy(static fn (IndustryPage $page): string => $page->family->value);

        $sections = [];

        foreach (IndustryFamily::cases() as $family) {
            /** @var Collection<int, IndustryPage> $pages */
            $pages = $byFamily->get($family->value, new Collection);

            if ($pages->isNotEmpty()) {
                $sections[$family->value] = $pages;
            }
        }

        return $sections;
    }

    public function findBySlug(string $slug): ?IndustryPage
    {
        return IndustryPage::query()->where('slug', $slug)->first();
    }

    /**
     * The page an unknown slug used to be, if any — PIII-72 §A4's 301.
     *
     * ⚠️ **CALLED ONLY AFTER `findBySlug()` HAS MISSED**, which is what stops a
     * row that has been renamed back to an address it once had from redirecting
     * to itself. The model's own hook removes the current slug from its history
     * for the same reason; this ordering is the second half of that guard, and
     * either alone would leave a loop reachable.
     */
    public function redirectTargetFor(string $slug): ?IndustryPage
    {
        return IndustryPage::query()->whereJsonContains('old_slugs', $slug)->first();
    }

    /**
     * The 4–6 same-family siblings this page's strip links to.
     *
     * ⛔ **SAME FAMILY ONLY — PIII-72 §A2.2's *"cross-family links NEVER"*.** The
     * cluster is topical or it is nothing, and this method has no parameter that
     * could widen it.
     *
     * ⚠️ **THE ROTATION IS SEEDED BY THE SLUG AND IS THEREFORE STABLE.** A random
     * pick would give a crawler a different set of internal links on every fetch,
     * which reads as a page whose content changes at random. `crc32` of the slug
     * is deterministic across processes, machines and PHP versions in a way
     * `spl_object_hash`, `shuffle()` and `array_rand()` are not.
     *
     * @return Collection<int, IndustryPage>
     */
    public function siblingsFor(IndustryPage $page): Collection
    {
        /** @var list<IndustryPage> $family */
        $family = IndustryPage::query()
            ->where('family', $page->family->value)
            ->where('id', '!=', $page->id)
            ->orderBy('position')
            ->get()
            ->all();

        $count = count($family);

        if ($count === 0) {
            return new Collection;
        }

        $offset = (int) (crc32($page->slug) % $count);

        $rotated = array_merge(array_slice($family, $offset), array_slice($family, 0, $offset));

        return new Collection(array_slice($rotated, 0, self::MAX_SIBLINGS));
    }

    /**
     * The pages `sitemap-industries.xml` lists — CC-3 §4's one column, second
     * render.
     *
     * @return Collection<int, IndustryPage>
     */
    public function indexable(): Collection
    {
        return IndustryPage::query()
            ->where('index_mode', true)
            ->orderBy('position')
            ->get();
    }
}
