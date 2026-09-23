<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Enums\PriceListItemSource;
use App\Models\AssistantBrief;
use App\Modules\X163\Models\PriceBookItem;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * What a business charges, and the one line it is always said with — T176 P5.
 *
 * ⛔ **A READER OF `price_book_items` (WHICH X-163 OWNS), AND THE ONLY READER AND WRITER OF THE DISCLAIMER ON
 * `assistant_briefs`** — the latter **HELD THERE BY A LINT**
 * (`tests/Feature/Architecture/PricesTest.php`), on 624/1223's reasoning and
 * with a sharper motive than either. Two things live here that cannot live
 * anywhere else:
 *
 *  - **The review-before-live filter.** `confirmed_at` is nullable, so a second
 *    reader is one forgotten `whereNotNull` away from quoting a figure nobody
 *    reviewed — and an unconfirmed row looks exactly like a confirmed one coming
 *    out of the database, so nothing would say.
 *  - **The disclaimer's fallback.** Most businesses will never write their own,
 *    so `$brief->quote_disclaimer` is `null` for most of them; a second reader
 *    taking the column would conclude there is no disclaimer, when the platform
 *    default is very much said.
 *
 * ## R13 lives in what this returns, not in a flag it sets
 *
 * An empty {@see PriceList} means skill 4 is **absent** — the assistant takes
 * the question and hands it to the owner — rather than free to estimate. That is
 * the ordinary state for a business that has not filled the screen in, and
 * nothing here logs it, warns about it or substitutes anything for it.
 *
 * ## The two writers are deliberately not the same method
 *
 * {@see set()} is a person typing a price: it upserts, and it confirms on the
 * spot, because typing a figure *is* the review. {@see proposeFrom()} is a file:
 * it **inserts only**, leaves every row unconfirmed, and **never touches a row
 * that already exists**. Collapsing the two into one `updateOrCreate` would let
 * an upload silently move a live price — which is precisely the thing
 * review-before-live exists to prevent, arriving through the door marked
 * "convenience".
 */
final class PriceBook
{
    /**
     * The platform's own disclaimer, for a business that has not written one.
     *
     * ⚠️ **A REGISTRY KEY RATHER THAN A CONSTANT** (`38` Part 2: every seeded
     * line is admin-editable), and **read at read time rather than copied into
     * the row at provisioning** — `reviews.default_invite_threshold`'s shape
     * (1420). A tenant who has written their own has a stored line Ops cannot
     * move; a tenant who has not follows the platform when the wording improves.
     */
    private const string DISCLAIMER_KEY = 'assistant.quote_disclaimer';

    /**
     * The longest slug {@see PriceList::quote()} will take to the database.
     *
     * ⚠️ **THE ARGUMENT IS `TenantLinks::MAX_SLUG_LENGTH`'s.** The value arrives
     * from skill 4, which picked it out of what a member of the public asked
     * for, by way of a model. Refusing an implausible one before the query keeps
     * an untrusted string from becoming a scan, and the ceiling matches the
     * column.
     */
    private const int MAX_SLUG_LENGTH = 160;

    /**
     * The longest label the store will take — `price_list_items.label`'s width.
     *
     * ⛔ **A SEPARATE CONSTANT FROM THE SLUG'S, AND CONFLATING THE TWO WAS A REAL
     * DEFECT.** `set()` checked the label against the *slug* ceiling of 160,
     * which is wider than the label column, so a 150-character name passed every
     * check in PHP and died on the insert with Postgres's `value too long for
     * type character varying(120)` — a 500 on the owner's screen with nothing on
     * it naming the field. The screen's own `max:120` rule hid it, which is
     * exactly the shape 398 warns about: an outer guard refusing first leaves the
     * inner one unfalsifiable, and this service is the chokepoint that has to
     * hold for **every** caller.
     */
    private const int MAX_LABEL_LENGTH = 120;

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly PriceSheet $sheet,
    ) {}

    /**
     * Everything this business will let its assistant quote.
     *
     * ⛔ **CONFIRMED ROWS ONLY, AND {@see PriceList::of()} REFUSES ANY OTHER.**
     * Two independent layers over one gate: dropping the filter below does not
     * leak a proposal, it raises — which is what makes the mutation red rather
     * than green.
     */
    public function list(): PriceList
    {
        Tenancy::idOrFail();

        $entries = [];

        foreach ($this->query()->where('is_confirmed', true)->where('is_sample', false)->orderBy('service_name')->get() as $item) {
            $entry = $this->toEntry($item);

            $entries[$entry->slug] = $entry;
        }

        return PriceList::of($this->disclaimer(), $entries);
    }

    /**
     * The rows an upload proposed and nobody has confirmed yet.
     *
     * For the editor, and for nothing else — these are not quotable and the type
     * says so, because {@see PriceList} will not hold one.
     *
     * @return list<PriceListEntry>
     */
    public function awaitingReview(): array
    {
        Tenancy::idOrFail();

        $entries = [];

        foreach ($this->query()->where('is_confirmed', false)->orderBy('id')->get() as $item) {
            $entries[] = $this->toEntry($item);
        }

        return $entries;
    }

    /**
     * The line said with every quote — the business's own, or the platform's.
     *
     * ⚠️ **NEVER EMPTY.** R13 requires a disclaimer with every price, so there is
     * no state in which this returns nothing; the CHECK on the column makes a
     * blank stored line unstorable and this makes an absent one the platform's.
     */
    public function disclaimer(): string
    {
        Tenancy::idOrFail();

        $stored = $this->brief()?->quote_disclaimer;

        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        $seed = $this->defaults->value(self::DISCLAIMER_KEY);

        return is_string($seed) ? $seed : '';
    }

    /**
     * Whether skill 4 is grounded (R13).
     */
    public function groundsQuoting(): bool
    {
        return ! $this->list()->isEmpty();
    }

    /**
     * Set a price, or move one the business already has.
     *
     * ⚠️ **NAMING IT AGAIN MOVES IT, AND RENAMING IT ADDS A SECOND ONE** — the
     * slug is the identity, exactly as it is for a shared document (P6). That is
     * the honest reading of "the name a customer would ask for", and it is why
     * this screen offers set and remove rather than an edit box per row.
     *
     * @param  ?int  $maxCents  The top of a range, or null for a flat figure.
     */
    public function set(string $label, int $minorUnits, ?int $maxCents = null): PriceListEntry
    {
        Tenancy::idOrFail();

        $name = trim($label);

        if ($name === '') {
            throw new InvalidArgumentException('A price needs the name of the job it is for.');
        }

        if (mb_strlen($name) > self::MAX_LABEL_LENGTH) {
            throw new InvalidArgumentException('That name is too long to say in a text message. Shorten it.');
        }

        $slug = $this->slugFor($name);

        if ($slug === '') {
            // Str::slug() strips everything it cannot transliterate, so a name
            // made entirely of punctuation or of an unsupported script leaves
            // nothing to address the price by. Refused rather than replaced with
            // a generated handle nobody typed and nobody could ask for —
            // `TenantLinks::addDocument()`'s call, for the same reason.
            throw new InvalidArgumentException(
                'That name has no letters or numbers in it, so a customer would have no way to ask '
                .'for it. Use the words they would use, like "Front door lockout".'
            );
        }

        $item = $this->query()->where('service_name', $name)->first() ?? new PriceBookItem;

        $item->forceFill([
            'service_name' => $name,
            'price_cents' => $this->assertPrice($minorUnits, $maxCents),
            'price_max_cents' => $maxCents,
            'is_confirmed' => true,
            // ⛔ CONFIRMED ON THE SPOT, AND THAT IS NOT A SHORTCUT PAST THE
            // GATE. A person naming a price IS the review; asking them to
            // confirm what they have just typed is a second press, and a second
            // press teaches everybody to press through the first — including the
            // one that matters, on the proposals.
            'confirmed_at' => Carbon::now(),
        ])->save();

        return $this->toEntry($item);
    }

    /**
     * Stop quoting one job.
     *
     * ⚠️ **DELETED RATHER THAN DISABLED**, on `TenantLinks::remove()`'s
     * reasoning: a standing instruction's absence is its off state, and a
     * `disabled_at` beside `confirmed_at` would be a second way to be un-quotable
     * for two readers to disagree about.
     */
    public function remove(string $slug): void
    {
        Tenancy::idOrFail();

        $normalised = trim($slug);

        if ($normalised === '') {
            throw new InvalidArgumentException('Removing a price needs the name it is stored under.');
        }

        $this->query()->whereRaw("REPLACE(LOWER(service_name), ' ', '-') = ?", [$normalised])->delete();
    }

    /**
     * Read a price sheet and propose what it says. **Nothing becomes quotable.**
     *
     * ⛔ **INSERT-ONLY. A ROW THE BUSINESS ALREADY PRICES IS REPORTED, NEVER
     * OVERWRITTEN.** An upload that could move an existing figure would put an
     * unreviewed number under a name the owner had already reviewed, and the
     * screen would show the row as confirmed because it is — the review it
     * carries would be of the price it used to hold. The owner edits those two or
     * three by hand, which is thirty seconds and is the whole of the safety.
     *
     * @return array{proposed: int, alreadyPriced: int, unreadable: int}
     *
     * @throws InvalidArgumentException when the file is not readable as text
     */
    public function proposeFrom(string $bytes): array
    {
        Tenancy::idOrFail();

        $reading = $this->sheet->read($bytes);

        $currency = $this->currency();
        $proposed = 0;
        $alreadyPriced = 0;
        $unreadable = $reading->unreadableLines;

        // ⚠️ THE SLUGS ARE READ ONCE RATHER THAN QUERIED PER ROW — two hundred
        // rows is two hundred round trips otherwise — and the read is the
        // tenant-scoped query, so a name another business prices is invisible
        // here and cannot collide.
        $taken = $this->query()
            ->pluck('service_name')
            ->map(fn (string $name): string => $this->slugFor($name))
            ->all();

        foreach ($reading->rows as $row) {
            $slug = $this->slugFor($row['label']);

            if ($slug === '') {
                // A label the slugger cannot make a handle out of — see set().
                // Counted with the lines we could not read rather than with the
                // ones already priced, because it is neither.
                $unreadable++;

                continue;
            }

            if (in_array($slug, $taken, true)) {
                $alreadyPriced++;

                continue;
            }

            $item = new PriceBookItem;

            $item->forceFill([
                'service_name' => $row['label'],
                'price_cents' => $row['minorUnits'],
                'price_max_cents' => $row['maxCents'],
                'is_confirmed' => false,
                // ⛔ THE WHOLE OF REVIEW-BEFORE-LIVE IS THIS FALSE.
                'confirmed_at' => null,
            ])->save();

            $taken[] = $slug;
            $proposed++;
        }

        return [
            'proposed' => $proposed,
            'alreadyPriced' => $alreadyPriced,
            'unreadable' => $unreadable,
        ];
    }

    /**
     * A person says one proposed row may be quoted.
     */
    public function confirm(string $slug): void
    {
        Tenancy::idOrFail();

        $normalised = trim($slug);

        if ($normalised === '') {
            throw new InvalidArgumentException('Confirming a price needs the name it is stored under.');
        }

        // ⚠️ SCOPED TO THE UNCONFIRMED ROWS, so that confirming twice does not
        // rewrite the timestamp on a figure that has been live for a month —
        // `confirmed_at` is the record of when it became quotable, and moving it
        // would erase the one thing anybody asks for after a wrong quote.
        $this->query()
            ->where('is_confirmed', false)
            ->whereRaw("REPLACE(LOWER(service_name), ' ', '-') = ?", [$normalised])
            ->update([
                'is_confirmed' => true,
                'confirmed_at' => CarbonImmutable::now(),
            ]);
    }

    /**
     * Throw away everything an upload proposed.
     *
     * ⚠️ **THE OTHER HALF OF REVIEW-BEFORE-LIVE, AND IT IS NOT OPTIONAL.** An
     * owner who uploads the wrong file needs one press that removes all of it;
     * without it the only way out of forty bad proposals is forty presses, and
     * the twenty-first is where somebody confirms one by accident.
     *
     * @return int how many were discarded
     */
    public function discardProposals(): int
    {
        Tenancy::idOrFail();

        return $this->query()->where('is_confirmed', false)->delete();
    }

    /**
     * Write the business's own disclaimer, or go back to the platform's.
     *
     * ⚠️ **NULL AND BLANK BOTH MEAN "USE OURS"**, because there is no state in
     * which R13 lets a quote go out with no line at all. An owner who empties the
     * box has said "yours is fine", not "say nothing".
     */
    public function setDisclaimer(?string $line): void
    {
        Tenancy::idOrFail();

        $trimmed = $line === null ? '' : trim($line);

        $this->briefForWriting()->forceFill([
            'quote_disclaimer' => $trimmed === '' ? null : $trimmed,
        ])->save();
    }

    /**
     * Whether the disclaimer in force is the business's own words.
     *
     * The editor says which, because an owner looking at a filled-in box has no
     * other way to tell whose sentence it is.
     */
    public function disclaimerIsTheirOwn(): bool
    {
        Tenancy::idOrFail();

        $stored = $this->brief()?->quote_disclaimer;

        return is_string($stored) && trim($stored) !== '';
    }

    /**
     * @return Builder<PriceBookItem>
     */
    private function query(): Builder
    {
        return PriceBookItem::query();
    }

    private function brief(): ?AssistantBrief
    {
        return AssistantBrief::query()->first();
    }

    private function briefForWriting(): AssistantBrief
    {
        return $this->brief() ?? new AssistantBrief;
    }

    /**
     * ⚠️ **DERIVED FROM THE LABEL RATHER THAN TYPED**, on `CLAUDE.md`'s
     * no-toggles rule and P6's `addDocument()` precedent: a business naming a job
     * "Front door lockout" has said everything a handle needs, and a second field
     * asking for one is a support surface plus a second thing to fall out of step
     * with the first.
     */
    private function slugFor(string $label): string
    {
        return Str::limit(Str::slug($label), self::MAX_SLUG_LENGTH, '');
    }

    /**
     * @return int the low figure, having checked the pair makes a price
     */
    private function assertPrice(int $minorUnits, ?int $maxCents): int
    {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('A price cannot be less than nothing.');
        }

        if ($maxCents !== null && $maxCents <= $minorUnits) {
            throw new InvalidArgumentException(
                'The top of a price range has to be more than the bottom. Leave the second box empty '
                .'if this job has one price.'
            );
        }

        return $minorUnits;
    }

    /**
     * ⚠️ **THE PLATFORM CURRENCY, READ AT WRITE TIME AND STORED** —
     * `TenantLinks::currency()`'s reasoning verbatim. `18` §Money handling makes
     * the currency part of the value; reading it at *read* time instead would
     * re-denominate every price a tenant already set the day the registry seed
     * changes.
     */
    private function currency(): string
    {
        $stored = $this->defaults->value('billing.currency');

        return is_string($stored) && trim($stored) !== '' ? strtoupper(trim($stored)) : 'USD';
    }

    private function toEntry(PriceBookItem $item): PriceListEntry
    {
        return new PriceListEntry(
            $item->service_name,
            Str::slug($item->service_name),
            $item->price_cents,
            $item->price_max_cents,
            $this->currency(),
            PriceListItemSource::Manual,
            $item->confirmed_at,
        );
    }
}
