<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingTerm;
use App\Exceptions\AmbiguousPlanOffer;
use App\Models\PlanOffer;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlanOfferCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Which offer, if any, a price is quoted under right now (T176 P1).
 *
 * The only reader and writer of {@see PlanOffer} in `app/`, enforced by a
 * chokepoint lint in `tests/Feature/Architecture/RegistryTest.php` — the same
 * shape `DefaultsRegistry` has over `plan_entitlements`, and for the same reason:
 * `plan_offers` carries no row-level security, so nothing beneath the application
 * layer refuses a stray read, and a second writer would be a second place a live
 * price could be created.
 *
 * ## ⛔ NOTHING ASKS FOR AN OFFER BY NAME, AND THAT IS THE POINT
 *
 * There is no picker, no form field and no query parameter. R10's founder rate is
 * not a coupon somebody types — it is what this product costs during the founder
 * window, and R18 ends that window at an announced event. So the offer is
 * resolved from **the clock** and from nothing a request carries. `CLAUDE.md`'s
 * "never add a tenant-facing toggle" is the general rule; the specific one is
 * that an offer key accepted from a form is a price accepted from a form.
 *
 * ## ⚠️ AMBIGUITY IS REFUSED RATHER THAN RESOLVED
 *
 * Two offers live on the same term is an operator error with no correct
 * tiebreak — "the newest one" and "the cheapest one" are both defensible and
 * both charge somebody a price nobody chose. This throws, loudly, at quote time.
 * The database cannot express the constraint (windows overlap across different
 * keys), so it is enforced here and driven red by a test.
 *
 * ⛔ **AND SINCE 4342 IT IS ALSO REFUSED AT THE POINT IT WOULD BE CREATED.**
 * Throwing at quote time on its own made `offers:sync` able to write the row
 * that 500s the public home page: the insert succeeded, the command printed
 * `Wrote:` and exited 0, and from that second every visitor and every checkout
 * met an exception. {@see self::sync()} therefore re-reads the live set inside
 * its own transaction and rolls back, so the deploy that would have broken the
 * site fails instead — and {@see AmbiguousPlanOffer} is a named
 * type so that the marketing page can degrade to the retail schedule while
 * nothing on the charging path may.
 *
 * ## ⚠️ EVERY WRITE IS AUDITED, THROUGH THE REGISTRY'S OWN TRAIL (4344)
 *
 * Opening or closing an offer changes what every future signup is charged, and
 * `registry_changes` is this codebase's platform-scoped before/after log — the
 * one `DefaultsRegistry::set()` writes for a value move, named in `TenancyTest`'s
 * exemption list as exactly that trail (509, 741). A live price table with no
 * change log and no actor was the one thing this table had that the registry
 * beside it did not.
 */
final class PlanOffers
{
    /**
     * The actor recorded against a seeded offer row.
     *
     * `SyncDefaultsRegistry::ACTOR`'s reasoning unchanged: nobody typed these
     * figures, the catalogue did, and a row claiming a person opened the founder
     * window would be worse than one saying where it came from.
     */
    public const string SEED_ACTOR = 'offers:sync';

    /**
     * The `registry_changes.setting_key` prefix for an offer row.
     *
     * Dotted and area-first, the same convention `platform_settings` uses, so
     * that the one screen rendering this log (`Admin\StaffActivity`) shows
     * `plan_offers.founder.monthly` beside `billing.trial_days` rather than a
     * second spelling of the same idea.
     */
    private const string AUDIT_PREFIX = 'plan_offers.';

    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    /**
     * The live offer for one term, or null for the registry's retail price.
     *
     * ⚠️ **NULL IS THE ORDINARY ANSWER AND NOT AN ERROR.** No live offer means
     * the plan is sold at the schedule in `plan_entitlements` — which is what
     * every caller did before this table existed, and what every caller does
     * again the moment somebody closes the founder window.
     *
     * @throws AmbiguousPlanOffer Two offers are live on this term at once.
     */
    public function currentFor(BillingTerm $term, ?Carbon $at = null): ?PlanOffer
    {
        return $this->liveAt($at)[$term->value] ?? null;
    }

    /**
     * Every live offer, keyed by term value.
     *
     * ⚠️ **ONE QUERY FOR BOTH TERMS, BECAUSE THE MARKETING HOME QUOTES FOUR
     * PRICES.** That page carries row 1's LCP gate and `PlanPricing`'s memo exists
     * for exactly this reason (519): a lookup per price would put one needless
     * round trip per figure on the page the gate is measured against.
     *
     * The window is filtered in SQL rather than by loading the table and asking
     * each row whether it is live — the index is on those two columns — and the
     * boundary is half-open on purpose: `opens_at <= now < closes_at`.
     *
     * ⛔ **THERE IS NO SECOND IMPLEMENTATION FOR IT TO AGREE WITH, AND THIS
     * PARAGRAPH SAID THERE WAS UNTIL 2026-08-29** (12331). It cited
     * `PlanOffer::isLiveAt()` — **a method the model has never carried** — and
     * concluded *"the two must agree, so the model's half-open boundary is
     * reproduced here deliberately"*. {@see PlanOffer} carries `price()`,
     * `additionalLocationPrice()`, its casts and a `deleting` refusal, **and no
     * window predicate of any kind**. The comparison is stated **once**, in the
     * query below. ⚠️ **The boundary is still deliberate**; what was false was
     * the reassurance that something else states it too — the reading that
     * sends a reader off to check an agreement that cannot disagree.
     *
     * @return array<string, PlanOffer>
     *
     * @throws AmbiguousPlanOffer Two offers are live on one term at once.
     */
    public function liveAt(?Carbon $at = null): array
    {
        $at ??= Carbon::now();

        /** @var list<PlanOffer> $rows */
        $rows = PlanOffer::query()
            ->where('opens_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('closes_at')->orWhere('closes_at', '>', $at);
            })
            ->orderBy('key')
            ->get()
            ->all();

        $byTerm = [];

        foreach ($rows as $row) {
            $term = $row->term->value;

            if (isset($byTerm[$term])) {
                throw AmbiguousPlanOffer::between($term, $byTerm[$term]->key, $row->key);
            }

            $byTerm[$term] = $row;
        }

        return $byTerm;
    }

    /**
     * Load {@see PlanOfferCatalog} into `plan_offers`, writing only what is absent.
     *
     * ⛔ **IT NEVER REFRESHES A ROW THAT EXISTS, AND THAT IS THE WHOLE OF ITS
     * SAFETY.** `SyncDefaultsRegistry` makes the same promise for a different
     * reason — there, an operator edits a budget — and here the edit that must
     * survive a deploy is the one that **ends the founder window**. A sync that
     * refreshed the seeded row would set `closes_at` back to null on the next
     * deploy after somebody closed it, and every signup from that morning would
     * be sold at the founder rate again with nothing on any screen to notice.
     *
     * ⚠️ **THIS SENTENCE READ "IT NEVER UPDATES A ROW THAT EXISTS" UNTIL
     * 2026-08-24 AND THE ARGUMENT IS UNCHANGED — ONE WRITE IS NOW MADE AND IT IS
     * THE ONE THAT CANNOT SELL ANYTHING** (9268). See the `closes_at` paragraph
     * below and {@see self::carryStatedClose()}; the old wording is kept because
     * the failure it names is the failure that arm is shaped around.
     *
     * ⛔ **BUT AN EXISTING ROW WHOSE *PRICES* DIFFER FROM THE CATALOGUE IS
     * REFUSED RATHER THAN SKIPPED (4343).** Skipping it silently made a
     * catalogue price edit a no-op: `RegistryTest` compares `CLAUDE.md` against
     * the catalogue and never against `plan_offers`, so an edit to both went
     * green, the deploy printed *"Nothing written"*, and production went on
     * charging the old price while every artefact a reader checks said the new
     * one. **Updating the row instead would be worse** — it is the one edit that
     * reopens a window somebody closed, two paragraphs up. A new price is a new
     * offer key, with the old window closed first.
     *
     * ⚠️ **THE WINDOW COLUMNS ARE DELIBERATELY NOT COMPARED BY
     * {@see self::refuseChangedPrices()}.** A stored `closes_at` differing from
     * the catalogue's is exactly what a close looks like, and refusing that would
     * fail every deploy after R18's flip. ⚠️ **`opens_at` is not compared
     * either**, and moving it in the catalogue therefore reaches no database that
     * already holds the row — the same one-way statement `closes_at` had until
     * 9268, left alone here because a moved opening rewrites when an offer was on
     * sale rather than ending one, and there is no direction of that edit that
     * cannot sell something.
     *
     * ⛔ **AND THE LIVE SET IS RE-READ INSIDE THE TRANSACTION (4342).** Adding a
     * transition offer on a term whose founder window is still open is an
     * operator error the database cannot express, and until this it produced a
     * successful deploy and a home page that 500s for every visitor.
     *
     * ⚠️ **WHAT THAT CHECK DOES NOT COVER: `--dry-run` (4528).** A dry run writes
     * nothing, so the set re-read here is the set that already existed — it sees a
     * collision that is *already* in the table and cannot see the one the
     * catalogue is about to create. `offers:sync --dry-run` therefore prints
     * *"Would write: transition:monthly"* and exits 0 for exactly the operator
     * error above, and the real run is what refuses. **That ordering is safe and
     * it is not honest.** It is written down rather than fixed because making the
     * dry run truthful means writing and rolling back, which is a different shape
     * from the one this method has — and a check that cannot run on the path it is
     * consulted from is 256's shape, so it is stated rather than assumed.
     *
     * ⛔ **BUT IT DOES CARRY A CLOSING DATE THE CATALOGUE STATES ONTO A ROW THAT
     * HAS NONE (9268).** Everything above is about a row somebody has *edited*,
     * and until this slice there was no way for the tree to end a window at all:
     * `closes_at` was honoured on the row this command **created** and ignored on
     * the row it **skipped**, so one statement in one file meant two different
     * things depending on how old the database was. The founder window is the
     * instance — the owner ended it on 2026-08-25 and every deployed install went
     * on quoting the founder rate however this repository was edited. See
     * {@see self::carryStatedClose()} for the three properties that make it safe.
     *
     * The return carries both acts: `written` is the `key:term` pairs this run
     * created, and `closed` maps a `key:term` pair to the instant this run ended
     * its window at.
     *
     * @return array{written: list<string>, closed: array<string, string>}
     *
     * @throws AmbiguousPlanOffer The catalogue would put two offers on one term.
     * @throws RuntimeException An existing row states a different price, or the
     *                          catalogue states a close at or before its opening.
     */
    public function sync(bool $dryRun = false): array
    {
        $written = [];
        $closed = [];

        DB::transaction(function () use ($dryRun, &$written, &$closed): void {
            foreach (PlanOfferCatalog::offers() as $offer) {
                $term = $offer['term'];

                $existing = PlanOffer::query()
                    ->where('key', $offer['key'])
                    ->where('term', $term->value)
                    ->first();

                if ($existing instanceof PlanOffer) {
                    $this->refuseChangedPrices($existing, $offer);

                    $endsAt = $this->carryStatedClose($existing, $offer, $dryRun);

                    if ($endsAt !== null) {
                        $closed[$offer['key'].':'.$term->value] = $endsAt->toIso8601String();
                    }

                    continue;
                }

                $written[] = $offer['key'].':'.$term->value;

                if ($dryRun) {
                    continue;
                }

                $row = PlanOffer::query()->create([
                    ...$offer,
                    'term' => $term->value,
                    'opens_at' => Carbon::parse($offer['opens_at']),
                    'closes_at' => $offer['closes_at'] === null
                        ? null
                        : Carbon::parse($offer['closes_at']),
                ]);

                $this->audit($row, null, self::SEED_ACTOR);
            }

            $this->assertNoCollision();
        });

        return ['written' => $written, 'closed' => $closed];
    }

    /**
     * End a stored window at the instant the catalogue states, when — and only
     * when — nobody has stated one already.
     *
     * ⛔ **THREE PROPERTIES, AND THE FIRST IS THE ONE THAT MAKES THIS SAFE AT
     * ALL: IT ONLY EVER WRITES OVER `NULL`.** A null `closes_at` is not a
     * decision anybody made — it is the absence of one, the fail-open state
     * `PlanOfferCatalog` calls out in its own docblock. A non-null one is
     * somebody's dated flip, and this method cannot touch it in either
     * direction: it cannot move a close later (which would put a price back on
     * sale, the failure 4641 closed in `close()` and the one {@see self::sync()}'s
     * own docblock refuses two paragraphs up) and it cannot move one earlier
     * (which would flip a price ahead of the date that was announced). ⚠️ **So
     * an operator who wants a window kept open past the catalogue's date has a
     * supported way to say so** — `offers:close <key> --at=<later>` writes a
     * non-null date and this arm never looks at that row again.
     *
     * ⛔ **SECOND: IT IS MONOTONIC IN THE DIRECTION THAT CANNOT SELL ANYTHING.**
     * Every write it can make narrows a window from *unbounded* to *bounded*.
     * There is no input to this method that widens one, which is why it may run
     * on every deploy where an unconditional refresh may not. `CloseOffer`'s
     * docblock says a deploy step that closed windows *"would be the
     * sync-reopens-a-window failure with its sign flipped"*, and that is right
     * about a step that closes windows **as an act**; this closes them only where
     * the catalogue — the tree's own statement of what this product offers —
     * states the instant, and where nothing else has spoken.
     *
     * ⛔ **THIRD: A CLOSE AT OR BEFORE THE OPENING IS REFUSED HERE AND NOT LEFT
     * TO THE CHECK.** `plan_offers_window_is_ordered` catches it, and its message
     * names a constraint rather than the two dates a person mistyped — the same
     * reasoning `close()` gives for refusing it a second time in PHP. Refused
     * inside the transaction, so a deploy fails rather than half-closing a key.
     *
     * ⚠️ **A PAST INSTANT IS NOT REFUSED**, and `close()` does not refuse one
     * either. The window really did end at the announced date; a deploy arriving
     * after it is late, not wrong, and refusing would leave the offer live for
     * exactly as long as nobody noticed.
     *
     * @param  array{key: string, term: BillingTerm, price_cents: int, additional_location_cents: int, price_currency: string, instalment_payments: ?int, opens_at: string, closes_at: ?string}  $offer
     * @return ?Carbon The instant this run ended that row's window at — or would
     *                 have, on a dry run. Null when it did nothing.
     *
     * @throws RuntimeException The stated close is at or before the opening.
     */
    private function carryStatedClose(PlanOffer $existing, array $offer, bool $dryRun): ?Carbon
    {
        if ($offer['closes_at'] === null || $existing->closes_at !== null) {
            return null;
        }

        $at = Carbon::parse($offer['closes_at']);

        if ($at->lessThanOrEqualTo($existing->opens_at)) {
            throw new RuntimeException(
                "The catalogue closes the `{$offer['key']}` offer's {$offer['term']->value} window at "
                .$at->toIso8601String().', which is not after it opened at '
                .$existing->opens_at->toIso8601String().'. A window that closes before it opens is '
                .'one nothing was ever offered in, and it reads on every screen exactly like a '
                .'promotion that has ended.'
            );
        }

        if ($dryRun) {
            return $at;
        }

        $before = $this->stateOf($existing);

        $existing->forceFill(['closes_at' => $at])->save();

        $this->audit($existing, $before, self::SEED_ACTOR);

        return $at;
    }

    /**
     * Refuse a live set with two offers on one term, at every instant the
     * catalogue can make one.
     *
     * ⚠️ **"NOW" IS NOT ENOUGH AND CHECKING ONLY IT WOULD BE 256's SHAPE.** The
     * operator error this exists for is a transition offer that opens *next
     * week* on a term whose founder window has no closing date — which is
     * invisible today and total on the morning it opens. Each catalogue row's
     * own `opens_at` is therefore probed as well, because those are the instants
     * this command can create a collision at.
     *
     * @throws AmbiguousPlanOffer
     */
    private function assertNoCollision(): void
    {
        $instants = [Carbon::now()];

        foreach (PlanOfferCatalog::offers() as $offer) {
            $instants[] = Carbon::parse($offer['opens_at']);
        }

        foreach ($instants as $instant) {
            $this->liveAt($instant);
        }
    }

    /**
     * Refuse an existing row whose prices no longer match the catalogue.
     *
     * @param  array{key: string, term: BillingTerm, price_cents: int, additional_location_cents: int, price_currency: string, instalment_payments: ?int, opens_at: string, closes_at: ?string}  $offer
     *
     * @throws RuntimeException
     */
    private function refuseChangedPrices(PlanOffer $existing, array $offer): void
    {
        $stated = [
            'price_cents' => $existing->price_cents,
            'additional_location_cents' => $existing->additional_location_cents,
            'price_currency' => $existing->price_currency,
            'instalment_payments' => $existing->instalment_payments,
        ];

        $wanted = [
            'price_cents' => $offer['price_cents'],
            'additional_location_cents' => $offer['additional_location_cents'],
            'price_currency' => $offer['price_currency'],
            'instalment_payments' => $offer['instalment_payments'],
        ];

        if ($stated === $wanted) {
            return;
        }

        $differences = [];

        foreach ($wanted as $column => $value) {
            if ($stated[$column] !== $value) {
                $differences[] = $column.': '.var_export($stated[$column], true)
                    .' stored, '.var_export($value, true).' in the catalogue';
            }
        }

        throw new RuntimeException(
            "The `{$offer['key']}` offer's {$offer['term']->value} row already exists and states a "
            .'different price ('.implode('; ', $differences).'). This sync will not update it: '
            .'the row is the record of what was on the table while people were buying, and '
            .'rewriting it would reprice a live window with nothing on any screen to notice. '
            .'Open a NEW offer key at the new price and close this one with `offers:close`.'
        );
    }

    /**
     * End an offer's window — R18's "dated, announced flip".
     *
     * ⚠️ **IT MOVES `closes_at` AND NOTHING ELSE.** The prices on the row stay
     * exactly as they were, because the row is the record of what was being
     * offered while people were buying, and every subscription sold under it
     * already carries its own agreed price (3443). Closing an offer therefore
     * reprices nobody: it changes what the *next* signup is quoted, which is the
     * entire meaning of the word.
     *
     * ⚠️ **THE ACTOR IS REQUIRED AND HAS NO DEFAULT (4344).** This is the one
     * call that ends R18's window, it changes what every future signup is
     * charged, and until 4344 it wrote no record of who did it or of what the
     * row said before. `DefaultsRegistry::set()` has taken an actor since the
     * day it existed for the same reason.
     *
     * ⛔ **AND SINCE 4641 IT CANNOT RE-OPEN A WINDOW THAT HAS ALREADY ENDED.**
     * This method wrote `closes_at` unconditionally and refused only a date at or
     * before `opens_at` — so `offers:close founder --at=<some later date>`, run a
     * second time against a window that closed last month, moved the closing date
     * **forward** and put the founder rate back on sale from that moment until
     * the new date. It reads on the console as a close, it prints *"Closed 2
     * rows"*, and it is a price cut nobody asked for. ⚠️ **It is the exact
     * failure `sync()` is written to prevent** — its own docblock calls
     * refreshing a seeded row *"the one edit that reopens a window somebody
     * closed"* — reachable through the command built to close them (4345).
     *
     * ⚠️ **`$reopen` IS THE ESCAPE AND IT IS DELIBERATELY EXPLICIT.** Closing an
     * hour too early is a real mistake with a real remedy, and refusing it
     * outright would send somebody to `tinker` — which is the state 4345 existed
     * to end. What is refused is doing it *by accident*: the caller has to say
     * the word, and the audit row records the same actor either way.
     *
     * ⚠️ **A WINDOW WHOSE CLOSE IS STILL IN THE FUTURE MAY BE MOVED FREELY**, and
     * that is not an oversight. R18's flip is *"dated and announced"*, so
     * scheduling one and then rescheduling it is the ordinary use of this method;
     * the window never stops being live in between, so nothing is resurrected and
     * nobody is sold a price that had ended.
     *
     * @param  string  $actor  Who ended the window — `offers:close` from the
     *                         console, or `user:{id}` if a screen ever calls it.
     * @param  bool  $reopen  Put a window that has **already ended** back on sale.
     *                        ⛔ Fail-closed by default: an offer whose `closes_at`
     *                        is in the past is refused without it.
     * @return int How many rows were closed.
     *
     * @throws RuntimeException The instant given is not in the future of the
     *                          window, or the window has already ended and
     *                          `$reopen` was not asked for.
     */
    public function close(string $key, Carbon $at, string $actor, bool $reopen = false): int
    {
        $closed = 0;

        DB::transaction(function () use ($key, $at, $actor, $reopen, &$closed): void {
            /** @var list<PlanOffer> $rows */
            $rows = PlanOffer::query()->where('key', $key)->orderBy('term')->get()->all();

            $now = Carbon::now();

            foreach ($rows as $row) {
                if (! $reopen && $row->closes_at instanceof Carbon && $row->closes_at->lessThanOrEqualTo($now)) {
                    // ⛔ THE WINDOW IS OVER. Writing any new date here rewrites
                    // the record of what was on the table while people were
                    // buying — and a date in the future puts the offer back on
                    // sale, which is the one thing this whole table is arranged
                    // to prevent. Refused for the pair of rows together: the
                    // transaction rolls back, so a key whose monthly row has
                    // ended and whose annual row has not closes neither, and the
                    // operator is told rather than left with half a change.
                    throw new RuntimeException(
                        "The `{$key}` offer's {$row->term->value} window already ended at "
                        .$row->closes_at->toIso8601String().', so closing it again would rewrite '
                        .'what was on sale while people were buying — and a later date would put '
                        .'it back on sale. Pass --reopen if that is genuinely what you mean.'
                    );
                }

                if ($at->lessThanOrEqualTo($row->opens_at)) {
                    // The database CHECK refuses this too. It is refused here as
                    // well because the message a person needs is not
                    // `plan_offers_window_is_ordered`, and because a closing date
                    // before the opening date is the one typo that reads as a
                    // successful close: the offer stops being quoted, so the
                    // screen looks right, and the row says the window never
                    // existed.
                    throw new RuntimeException(
                        "An offer cannot close before it opened: '{$key}' opened at "
                        .$row->opens_at->toIso8601String().' and was asked to close at '
                        .$at->toIso8601String().'.'
                    );
                }

                $before = $this->stateOf($row);

                $row->forceFill(['closes_at' => $at])->save();

                $this->audit($row, $before, $actor);

                $closed++;
            }
        });

        return $closed;
    }

    /**
     * Record an offer row's before and after in the platform change log.
     *
     * ⚠️ **THROUGH `DefaultsRegistry` RATHER THAN `RegistryChange::create()`,
     * AND THAT IS NOT A DETOUR.** A chokepoint lint in `RegistryTest` names the
     * registry as the only reader and writer of that model, for the reason this
     * table has its own chokepoint: a second writer is a second place the
     * before/after contract can be got wrong. Writing the row here would have
     * meant adding this file to that permitted list — a lint weakened to save an
     * import, which is the shape 4329 deleted one file over.
     *
     * @param  ?array<string, mixed>  $before  Null on a seed: the row did not exist.
     */
    private function audit(PlanOffer $row, ?array $before, string $actor): void
    {
        $this->registry->recordPlatformChange(
            self::AUDIT_PREFIX.$row->key.'.'.$row->term->value,
            $before,
            $this->stateOf($row),
            $actor,
        );
    }

    /**
     * What an offer row says, as the change log stores it.
     *
     * ⚠️ **THE WHOLE ROW RATHER THAN THE COLUMN THAT MOVED.** `closes_at` is the
     * only column any caller edits today, and a log entry saying only that a
     * date changed would not answer the question somebody asks a year later:
     * *what were we selling, at what price, while that window was open*. The row
     * is small and every field on it is a price term.
     *
     * @return array<string, mixed>
     */
    private function stateOf(PlanOffer $row): array
    {
        return [
            'key' => $row->key,
            'term' => $row->term->value,
            'price_cents' => $row->price_cents,
            'additional_location_cents' => $row->additional_location_cents,
            'price_currency' => $row->price_currency,
            'instalment_payments' => $row->instalment_payments,
            'opens_at' => $row->opens_at->toIso8601String(),
            'closes_at' => $row->closes_at?->toIso8601String(),
        ];
    }
}
