<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\ActuationTier;
use App\Enums\DataClassification;
use App\Enums\T3InjectionKind;
use App\Enums\T3PayloadRefusal;
use App\Models\Business;
use App\Services\Pixel\PixelKeys;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;

/**
 * What the T3 module is told to do, and who is refused before it is built —
 * `BUILD-PLAN` §2.11.3 slice I.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHY THIS IS A SECOND MODULE AND NOT A CHANGE TO THE COLLECTOR
 * ---------------------------------------------------------------------------
 * §2.11.5 conflict 4, which is the architecture of this slice rather than a note
 * beside it: **the pixel bundle is a pure collector**, its lints assert that, and
 * W2's whole argument depends on it.
 *
 * ⚠️ **THE BUNDLE IS NOT NAMED BY PATH HERE, AND THAT IS NOT SQUEAMISHNESS** —
 * `PixelTest`'s *"exactly one file may deliver the pixel bundle"* lint fails the
 * build when any file under `app/`, `routes/` or `resources/views/` spells it,
 * because that is what a second delivery would look like. It caught this
 * docblock, as it caught `PixelCollector`'s and `L0Receipt`'s before it; the
 * lint cannot tell a reference from a mention and widening its allowlist for a
 * mention is 511's failure. Injection needs a payload fetch
 * and DOM writes. Teaching the collector to do them *"would put DOM-writing code
 * in every visitor's browser on every tenant site whether or not actuation is
 * on"* — every page of every tenant, including the ones that never bought site
 * control and the ones we refuse to actuate at all.
 *
 * So the client half is `resources/js/actuate.js`, a separate entry inside the
 * same 14 KB composed budget, and **the server decides who gets the code**: the
 * module route asks this service the same question the payload route does, and
 * serves an empty body to a tenant with nothing live. A visitor on a site with
 * no T3 change sets downloads no DOM-writing code at all.
 *
 * ---------------------------------------------------------------------------
 * THE ORDER OF THE GATES, WHICH IS THE COLLECTOR'S ORDER FOR THE COLLECTOR'S
 * REASON
 * ---------------------------------------------------------------------------
 *   1. `Tenancy::forgetAll()` — unconditionally first. The tenant lives in a
 *      PostgreSQL session variable that outlives a request whenever a connection
 *      is reused, so an early return without clearing would let this request
 *      inherit a tenant it has no claim to. Here that means serving one
 *      business's pages to another's website.
 *   2. public key → business, through {@see PixelKeys::resolve()}. **The key is
 *      `pixel_keys` and never `businesses.pixel_tenant_id`** (4961), which was
 *      dropped precisely because the row is invisible to the query that
 *      establishes the tenant.
 *   3. ⛔ **`Phi` refused, before anything is read.** Rule 24. See
 *      {@see T3PayloadRefusal::HealthTenant} and {@see T3FaqBlock}.
 *   4. paused, then suspended — row 5's *"all sending + actuation off"*.
 *   5. the live T3 change sets, typed into operations, or nothing.
 *
 * ⚠️ **EVERY ONE OF THOSE IS DRIVEN AT THIS SERVICE IN THE TESTS AND NOT ONLY
 * THROUGH THE ROUTE** (4965): the first draft of the collector's PHI test passed
 * for the wrong reason because an outer layer refused first, and this endpoint
 * has the same shape — a route, a limiter, a resolver and a gate, any of which
 * could be the thing that said no.
 *
 * ⚠️ **NOTHING APPLIES A T3 CHANGE SET TODAY**, so every real key resolves to
 * `NothingLive`. That is stated rather than left to be discovered: the writer is
 * slice D's publishing pipeline and slice G's adapter, `actuation.enabled` is
 * seeded false until slice H merges, and this endpoint is the serving half
 * waiting for them. A test that could not tell `NothingLive` from a leak would
 * be worthless, which is why the refusal is a named case.
 */
final readonly class T3Payloads
{
    public function __construct(
        private PixelKeys $keys,
        private SiteChanges $changes,
        private TenantPause $pause,
        private TenantSuspension $suspension,
    ) {}

    /**
     * The payload for one public pixel key, or why there is none.
     *
     * ⚠️ **LEAVES THE TENANT ESTABLISHED ON PURPOSE** — the caller is a
     * controller whose request ends immediately afterwards, and clearing it here
     * would mean a caller that wanted to read anything else had to resolve the
     * key twice. `PixelKeys::resolve()`'s own docblock draws the same line
     * between a lookup and a request owner.
     */
    public function forKey(?string $key): T3Payload|T3PayloadRefusal
    {
        Tenancy::forgetAll();

        if (! is_string($key)) {
            return T3PayloadRefusal::UnknownKey;
        }

        $business = $this->keys->resolve($key);

        if (! $business instanceof Business) {
            return T3PayloadRefusal::UnknownKey;
        }

        Tenancy::set((int) $business->getKey());

        // ⛔ BEFORE ANY CHANGE SET IS READ, on `PixelCollector`'s own ordering
        // argument: a tenant refused wholesale must be refused before anything
        // about it is loaded, logged or counted.
        if ($business->data_classification === DataClassification::Phi) {
            return T3PayloadRefusal::HealthTenant;
        }

        if ($this->pause->isPaused($business)) {
            return T3PayloadRefusal::AccountPaused;
        }

        if ($this->suspension->isSuspended($business)) {
            return T3PayloadRefusal::AccountSuspended;
        }

        $payload = $this->build();

        return $payload->isEmpty() ? T3PayloadRefusal::NothingLive : $payload;
    }

    /**
     * The live T3 change sets, as operations, grouped by website and page.
     *
     * ⛔ **THE WEBSITE IS PART OF THE KEY AND THE PATH ALONE IS NOT ENOUGH**
     * (6044). `pixel_keys.business_id` is unique — one public key per business —
     * while every change set is written against a **location**, whose website is
     * `locations.website_url`. A tenant with two websites installs the same key
     * on both, so a payload keyed on the path alone had each site applying the
     * other's `/about`, `/contact` and `/services` operations. That is not a
     * cross-tenant leak and it is worse than it sounds anyway: what lands on the
     * page is a name, an address, opening hours and a rating describing **a
     * different premises**, published to search engines under the owner's own
     * domain.
     *
     * ⚠️ **A CHANGE SET WHOSE URL NAMES NO WEBSITE IS WITHHELD RATHER THAN
     * SERVED EVERYWHERE** — 5967's rule, which withheld a page signal for a
     * two-website tenant rather than attributing it wrongly. Serving it on every
     * host would be exactly the defect above with one row instead of two.
     */
    private function build(): T3Payload
    {
        /** @var array<string, array<string, list<T3Operation>>> $pages */
        $pages = [];

        foreach ($this->changes->live(ActuationTier::T3) as $set) {
            $operation = $this->operation($set);

            if (! $operation instanceof T3Operation) {
                continue;
            }

            // ⚠️ **ASKED AFTER THE TYPE AND NOT BEFORE**, so the log below names
            // only change sets this tier would otherwise have served: an
            // unrecognised `change_type` is ordinary and already silent.
            $host = self::hostOf($set->url);

            if ($host === null) {
                Log::warning('actuation.t3.unplaceable_change_set', [
                    'business_id' => Tenancy::id(),
                    'path' => self::pathOf($set->url),
                    'change_type' => $set->changeType,
                ]);

                continue;
            }

            $pages[$host][self::pathOf($set->url)][] = $operation;
        }

        return new T3Payload($pages);
    }

    /**
     * One change set as one typed operation, or null if this tier cannot express
     * it.
     *
     * ⚠️ **TWO DIFFERENT NULLS AND ONLY ONE OF THEM IS INTERESTING.** An
     * unrecognised `change_type` is ordinary — five tiers write into one table
     * and T1 writes kinds that mean nothing here — so it is skipped in silence.
     * A change type this tier *does* know, whose fields will not type, is a
     * change set somebody meant to publish and this endpoint refused: it is
     * logged, with the path and the type and **never a value**, which is 5529's
     * rule for the log driver applied to the same class of data.
     */
    private function operation(ChangeSet $set): ?T3Operation
    {
        $kind = T3InjectionKind::tryFrom($set->changeType);

        if ($kind === null) {
            return null;
        }

        $operation = match ($kind) {
            T3InjectionKind::JsonLd => T3JsonLdBlock::fromFields($set->after),
            T3InjectionKind::Meta => T3MetaUpsert::fromFields($set->after),
            T3InjectionKind::AltText => T3AltText::fromFields($set->after),
            T3InjectionKind::InternalLink => T3InternalLink::fromFields($set->after),
            T3InjectionKind::Faq => T3FaqBlock::fromFields($set->after),
        };

        if ($operation === null) {
            Log::warning('actuation.t3.untypeable_change_set', [
                'business_id' => Tenancy::id(),
                'path' => self::pathOf($set->url),
                'change_type' => $kind->value,
                'fields' => array_keys($set->after),
            ]);
        }

        return $operation;
    }

    /**
     * The path the module will match against `location.pathname`.
     *
     * ⚠️ **THE QUERY STRING IS DROPPED.** It is where a booking reference or an
     * email address ends up (5529), and no page is identified by one. A trailing
     * slash is normalised away on both sides of the wire, because `/services`
     * and `/services/` are one page to every CMS and two strings to a
     * comparison.
     *
     * ⛔ **THE HOST IS NOT DROPPED, AND THIS DOCBLOCK SAID IT WAS UNTIL 6044 WAS
     * FIXED.** It travels beside the path, from {@see self::hostOf()}: *"the
     * module is already running on it"* is true of one website and false of the
     * second one a tenant owns.
     */
    public static function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * The website the module will match against `location.hostname`, or null
     * when the change set does not name one.
     *
     * ⚠️ **NULL RATHER THAN THE EMPTY STRING, WHICH IS WHERE THIS DIFFERS FROM
     * ITS SIBLING.** `LocationWebsite::hostOf()` casts an absent host to `''`
     * because its callers are comparing two addresses a person pasted; here an
     * unnamed website is a change set nobody can place, and `''` would be a key
     * that matched no browser and read on a diff like a host.
     *
     * ⚠️ **THE PORT IS DROPPED BECAUSE `location.hostname` DROPS IT**, and the
     * comparison has to be of the same thing on both sides of a public network
     * hop. A business website on a non-default port is not a different website.
     *
     * ⚠️ **EXACT, APART FROM CASE — `www.` IS NOT NORMALISED AWAY AND THAT IS
     * DELIBERATE.** 6044 is a defect caused by guessing that two websites were
     * one, and the guess in the other direction has the same shape: an owner
     * whose recorded address is `https://example.test` while their site answers
     * on `www.example.test` gets nothing injected, which is the harmless
     * direction. `LocationWebsite::confirmAbout()` compares hosts on exactly
     * these terms — *"`www.` is a different host to WordPress and to us"*.
     *
     * ⚠️ **AN INTERNATIONALISED DOMAIN FAILS THE SAME CLOSED WAY**: a browser
     * reports `location.hostname` in its ASCII form, and a URL stored in unicode
     * parses to the unicode one, so the two never match and nothing is applied.
     * Stated rather than converted, because `idn_to_ascii()` here would be one
     * more place with an opinion about what a host is, and nothing in this
     * codebase writes an IDN website address today.
     */
    public static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return mb_strtolower(trim($host));
    }
}
