<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IndustryFamily;
use Carbon\CarbonImmutable;
use Database\Factories\IndustryPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One of the hundred industry pages — CC-3, from PIII-64A–E.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there:
 * marketing content about industries is our page, not a tenant's data, and a
 * `business_id` would mean every plumber holding a private copy of the page the
 * public reads.
 *
 * ## RENAME = REDIRECT, enforced here rather than remembered
 *
 * PIII-72 §A4: *"a slug is never renamed after indexing without a 301 written in
 * the same act — the row keeps `old_slugs[]` and the redirect table renders from
 * it. A moved page never becomes a dead page."* {@see self::booted()} is that
 * act, and it is a model hook rather than a discipline because the alternative is
 * a rule somebody follows until the day they are renaming three slugs at once.
 *
 * ⚠️ **A HOOK RATHER THAN AN `Observer` CLASS, ON `LegalDocument`'s PRECEDENT.**
 * There is no `app/Observers` directory in this codebase and no model uses
 * `#[ObservedBy]`; `LegalDocument`'s publish freeze — the closest thing to this in
 * shape — is a `booted()` hook. `CLAUDE.md`'s *follow existing conventions* beats
 * CC-3's word "observer", which is about the behaviour rather than the file.
 *
 * ⚠️ **IT FIRES ON `updating`, NOT ON `saved`.** `getOriginal('slug')` is the only
 * place the old value still exists, and by `saved` it is gone — the rename would
 * be recorded as pointing at itself.
 *
 * @property IndustryFamily $family
 * @property list<string> $trio
 * @property list<int> $faq_picks
 * @property list<string> $old_slugs
 * @property ?CarbonImmutable $updated_at
 */
final class IndustryPage extends Model
{
    /** @use HasFactory<IndustryPageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(function (self $page): void {
            if (! $page->isDirty('slug')) {
                return;
            }

            /** @var string $previous */
            $previous = $page->getOriginal('slug');

            $history = $page->old_slugs;

            // ⚠️ THE NEW SLUG IS REMOVED FROM ITS OWN HISTORY, AND THAT IS THE
            // RENAME-BACK CASE. `a → b → a` would otherwise leave `a` listed as a
            // former address of the row now living at `a`, so the 301 lookup
            // would match the row's *current* slug and redirect it to itself —
            // a loop on a public URL, produced by an edit that looks like an undo.
            $history = array_values(array_filter(
                $history,
                static fn (string $slug): bool => $slug !== $page->slug,
            ));

            if (! in_array($previous, $history, true)) {
                $history[] = $previous;
            }

            $page->old_slugs = $history;
        });
    }

    /**
     * The industry noun, which is what every link to this page says.
     *
     * PIII-72 §A2.2: *"anchors = the industry NOUN ('pool service,' 'locksmith'),
     * never 'click here.'"* Derived from the slug rather than stored, because the
     * slug IS the noun in this corpus and a second column would be a second thing
     * to keep true — and the one that goes stale is the one nobody renders while
     * reading the diff.
     */
    public function noun(): string
    {
        return str_replace('-', ' ', $this->slug);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'family' => IndustryFamily::class,
            'trio' => 'array',
            'faq_picks' => 'array',
            'old_slugs' => 'array',
            'index_mode' => 'boolean',
            'position' => 'integer',
            'updated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
