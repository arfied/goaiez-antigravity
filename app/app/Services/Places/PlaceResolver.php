<?php

declare(strict_types=1);

namespace App\Services\Places;

use App\Contracts\PlacesClient;
use App\Enums\PlaceResolutionRule;
use App\Exceptions\PlacesBudgetExhausted;
use App\Exceptions\PlacesRequestFailed;

/**
 * `24` §1.2.1's ladder: any Google link in, a `place_id` candidate out.
 *
 * ONE CODE PATH FOR TWO ROWS. `BUILD-PLAN` §4.3 warns that row 2 (the free
 * audit) and row 3 (GBP-01b) resolve the same thing from the same inputs, and
 * decision 197 settles that this ships whole in row 2 rather than split: "The
 * SSRF host allowlist and the confirm-then-persist contract are the
 * security-critical halves, and splitting them across two rows is exactly how
 * the second version appears."
 *
 * THE LADDER, TOP-DOWN, STOPPING AT THE FIRST ROW THAT YIELDS AN ID:
 *
 *   1  placeid= / place_id= in the query        the id, directly. No network
 *   2  maps.app.goo.gl or goo.gl/maps           resolve the redirect, re-run
 *   3  ftid= or !1s0x…:0x… inside data=         second hex half is the CID -> row 5
 *   4  cid=<decimal>                            the CID -> row 5
 *   5  name from /maps/place/<name>/, @lat,lng  Places Text Search, coord-biased
 *
 * ROWS 3–5 ARE THE COMMON CASE. `24` §1.2.1 is blunt: "A modern Maps share link
 * usually contains no `placeid=` at all... Any parser built only for the boss's
 * rows 1–2 and `cid=` will fail for most owners." The test suite leads with a
 * `maps.app.goo.gl` link carrying no `placeid=`, resolving end to end, for
 * exactly that reason (`BUILD-PLAN` §2.5.3).
 *
 * ROWS 3 AND 4 ALWAYS CONTINUE INTO ROW 5. There is no official CID →
 * `place_id` conversion and a CID is not accepted by the `writereview`
 * endpoint, so a CID is a *better search input*, never an answer. It is carried
 * onto the candidate because `24` §1.2.3 makes `google_cid` the permanent
 * identifier — place IDs change on listing merges, CIDs do not.
 *
 * NOTHING HERE PERSISTS ANYTHING. `24` §1.2.3: "Never save a resolved
 * `place_id` without that confirmation, however confident the match." This class
 * returns candidates; ConfirmedPlace is what writes, and it cannot be called
 * without a confirmation. See PlaceCandidate.
 */
final class PlaceResolver
{
    public function __construct(
        private readonly PlacesClient $places,
        private readonly ShortLinkResolver $shortLinks,
    ) {}

    /**
     * Run the ladder against a pasted URL.
     *
     * @param  bool  $followShortLink  Internal: false on the re-run after a
     *                                 redirect, so a shortener loop cannot recurse.
     */
    public function resolve(string $url, bool $followShortLink = true): ResolutionOutcome
    {
        $url = trim($url);

        // The allowlist is the first thing, before any parsing and long before
        // any socket (`24` §1.2.2). A rejected host never reaches the rest.
        if (! GoogleLinkHosts::allowsUrl($url)) {
            return ResolutionOutcome::unresolved('host_not_allowed');
        }

        // Row 1 — the id is right there. No network call, no cost.
        $placeId = $this->placeIdParameter($url);

        if ($placeId !== null) {
            return ResolutionOutcome::resolved($this->describe(
                $placeId,
                PlaceResolutionRule::PlaceIdParameter,
            ));
        }

        // Row 2 — a short link gives a URL, not an id. Resolve and re-run.
        if ($followShortLink && GoogleLinkHosts::isShortLink($url)) {
            $destination = $this->shortLinks->resolve($url);

            return $destination === null
                ? ResolutionOutcome::unresolved('short_link_unresolvable')
                : $this->resolve($destination, followShortLink: false);
        }

        // Rows 3 and 4 — a CID. Not an answer; a better search input.
        $cid = $this->featureIdCid($url) ?? $this->cidParameter($url);

        // Row 5 — name and coordinates into a coordinate-biased text search.
        // Rows 3 and 4 land here too, carrying their CID onto the candidate.
        return $this->textSearch($url, $cid);
    }

    /**
     * Resolve a business name a visitor typed, with no link involved.
     *
     * The free audit's entry point (`29` §6.2). It runs the same coordinate-free
     * text search as row 5 of the ladder and returns the same outcomes, so an
     * ambiguous name behaves identically whether it arrived as a name or inside
     * a Maps URL.
     *
     * NO HOST ALLOWLIST HERE, AND NOTHING IS MISSING. The allowlist in resolve()
     * is an SSRF control: that path takes a URL and may open a socket to it, so
     * the host has to be one of ours to reach. This path never dereferences
     * anything — the string is a search term that goes to Google in a request
     * body. Running it through GoogleLinkHosts would reject every real business
     * name, which is a good clue that the control does not belong here.
     *
     * What does apply, and is enforced above this: a length cap and a minimum
     * length in the form request, and autocomplete's own budget.
     */
    public function resolveName(string $query): ResolutionOutcome
    {
        $query = trim($query);

        if ($query === '') {
            return ResolutionOutcome::unresolved('no_pattern_matched');
        }

        return $this->searchByName($query, null);
    }

    /**
     * Row 1 — `placeid=` or `place_id=` in the query string.
     *
     * Google writes both spellings depending on the surface, and the
     * `writereview` link uses the one without the underscore.
     */
    private function placeIdParameter(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        foreach (['placeid', 'place_id'] as $key) {
            $value = $query[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Row 3 — the feature id, whose **second** hex half is the CID.
     *
     * Two spellings, both real: `ftid=0x…:0x…` as a query parameter, and the
     * `!1s0x…:0x…` token inside the `data=` blob of a modern share URL. The
     * second is far more common and is the one a rows-1-and-2 parser misses.
     *
     * The hex is converted with hexdec() rather than (int) — a CID is a 64-bit
     * value and every one of them overflows a base-10 int parse.
     */
    private function featureIdCid(string $url): ?string
    {
        if (preg_match('/[?&]ftid=0x[0-9a-f]+:0x([0-9a-f]+)/i', $url, $matches) === 1) {
            return $this->hexToDecimal($matches[1]);
        }

        if (preg_match('/!1s0x[0-9a-f]+:0x([0-9a-f]+)/i', $url, $matches) === 1) {
            return $this->hexToDecimal($matches[1]);
        }

        return null;
    }

    /**
     * Row 4 — `cid=<decimal>` in the query string.
     */
    private function cidParameter(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $cid = $query['cid'] ?? null;

        return is_string($cid) && ctype_digit($cid) ? $cid : null;
    }

    /**
     * Row 5 — the only rule that spends money.
     *
     * The name comes from `/maps/place/<name>/` and the bias from `@lat,lng`.
     * One unambiguous candidate is used; several are handed back for the owner
     * to choose between, per `24` §1.2.1 row 5.
     */
    private function textSearch(string $url, ?string $cid): ResolutionOutcome
    {
        $name = $this->placeNameFromPath($url);

        if ($name === null) {
            return ResolutionOutcome::unresolved('no_pattern_matched');
        }

        return $this->searchByName($name, $cid);
    }

    /**
     * The ladder's last rung, reachable without a link.
     *
     * Row 2's marketing home asks for a business *name*, not a Maps URL — `29`
     * §6.2's input is "Enter your business name". Everything below row 5 of the
     * ladder is therefore unreachable from that path, and the search half of row
     * 5 is the only part that applies.
     *
     * SHARED RATHER THAN COPIED, which is decision 197 holding at a smaller
     * scale than it was written for. 197 kept rows 2 and 3 on one resolver so
     * the SSRF allowlist and the confirm-then-persist contract could not diverge;
     * the same argument applies to how a search result becomes a candidate. A
     * second copy of this would be the place where "one match is resolved, more
     * than one is ambiguous" quietly stops being true on one of the two paths.
     */
    private function searchByName(string $name, ?string $cid): ResolutionOutcome
    {
        try {
            $results = $this->places->textSearch($name);
        } catch (PlacesBudgetExhausted) {
            // The cost cap said no. Not an error and not a dead end — the owner
            // gets `24` §1.2.4's ladder, and the wizard is never blocked.
            return ResolutionOutcome::unresolved('budget_exhausted');
        } catch (PlacesRequestFailed) {
            return ResolutionOutcome::unresolved('search_failed');
        }

        if ($results === []) {
            return ResolutionOutcome::unresolved('nothing_found');
        }

        $candidates = array_map(
            fn (PlaceSummary $place): PlaceCandidate => new PlaceCandidate(
                placeId: $place->placeId,
                rule: PlaceResolutionRule::TextSearch,
                displayName: $place->displayName,
                formattedAddress: $place->formattedAddress,
                googleCid: $cid,
                // ⚠️ ALREADY ON THE WIRE AND ALREADY PAID FOR — carrying them
                // costs nothing and changes no field mask. `textSearch()` asks
                // for `places.primaryType,places.types` today, and Google bills a
                // request at the highest SKU any requested field belongs to: the
                // mask already contains `places.displayName`, so the call is
                // billed **Text Search Pro** whatever happens to these two.
                // Verified against Google's live Place Details and Text Search
                // field lists on 2026-08-04 — `types` is an *Essentials* field
                // and `primaryType` a *Pro* one, and both appear in the Text
                // Search Pro list.
                //
                // Decision 477 recorded this as an open "field-mask and billing
                // decision". It was one when it was written and it is not one
                // now: what actually stopped the classifier seeing these was
                // this array literal dropping them on the floor.
                categories: $place->categories(),
            ),
            $results,
        );

        return count($candidates) === 1
            ? ResolutionOutcome::resolved($candidates[0])
            : ResolutionOutcome::ambiguous($candidates);
    }

    /**
     * The business name from `/maps/place/<name>/`, URL-decoded.
     *
     * Google writes spaces as `+` here rather than `%20`, so a raw urldecode is
     * right and rawurldecode is not — the difference turns "Bartlett+Plumbing"
     * into a search for a business with a plus sign in its name.
     */
    private function placeNameFromPath(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#/maps/place/([^/@]+)#', $path, $matches) !== 1) {
            return null;
        }

        $name = trim(urldecode($matches[1]));

        return $name === '' ? null : $name;
    }

    /**
     * A candidate for a `place_id` we already have, with no extra lookup.
     *
     * Rows 1's id is trusted but nameless, and `24` §1.2.3's confirm card needs
     * something a human can recognise. Details would supply it — and would cost
     * an Atmosphere call for a name. Slice E already fetches details for the
     * audit itself, so the label is filled there rather than paid for twice.
     *
     * ⚠️ IT CARRIES NO CATEGORIES EITHER, for the same reason and with a second
     * consequence: `PlaceConfirmation::confirm()` classifies from them, so a
     * business confirmed through row 1 gets no classification signal from this
     * path. That is the honest outcome of making no request — the alternative is
     * buying a Place Details call on the wizard's critical path to guess at
     * something the audit already answers for most tenants — but it is a hole in
     * the coverage rather than a proof of absence, and `PlaceCandidate` says so
     * where a reader of `[]` would look.
     */
    private function describe(string $placeId, PlaceResolutionRule $rule): PlaceCandidate
    {
        return new PlaceCandidate(placeId: $placeId, rule: $rule);
    }

    /**
     * Hex to decimal for 64-bit CIDs, in pure PHP.
     *
     * A CID is an unsigned 64-bit value, up to 18446744073709551615, while
     * PHP_INT_MAX is 9223372036854775807 — so roughly half of all CIDs overflow
     * an integer parse and `hexdec()` silently hands back a float that has
     * already lost digits. A CID that is off by a few is not a CID.
     *
     * Not bcmath, and not gmp: neither is declared in composer.json, so relying
     * on one would work here and fail on the first host that ships without it —
     * the exact shape of defect STAGE-0-NOTES §2 says only appears on a clean
     * start. Long multiplication on a digit array needs no extension and is
     * fifteen lines.
     */
    private function hexToDecimal(string $hex): string
    {
        /** @var list<int> $digits little-endian decimal digits */
        $digits = [0];

        foreach (str_split(strtolower($hex)) as $char) {
            $carry = (int) hexdec($char);

            foreach ($digits as $i => $digit) {
                $value = $digit * 16 + $carry;
                $digits[$i] = $value % 10;
                $carry = intdiv($value, 10);
            }

            while ($carry > 0) {
                $digits[] = $carry % 10;
                $carry = intdiv($carry, 10);
            }
        }

        return implode('', array_reverse(array_map('strval', $digits)));
    }
}
