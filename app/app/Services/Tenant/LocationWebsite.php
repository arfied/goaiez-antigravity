<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Jobs\ProbeLocationSiteJob;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\ShortLinks\ShortLinks;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The only writer of `locations.website_url` (5540).
 *
 * ⛔ **THE COLUMN SHIPPED WITH STAGE 0 AND HAD NO WRITER IN `app/` UNTIL THIS
 * CLASS** — only `LocationFactory` set it, which is `businesses.pixel_tenant_id`
 * rebuilt (4961) and carries the same consequence: every test of the actuation
 * tier scanner would have passed against a URL no real tenant has, and the
 * scanner would have reported "we do not know yet" for every tenant in
 * production with the suite green. **Check for a writer before depending on a
 * column** is `CLAUDE.md`'s rule and this is the sixteenth-and-something
 * instance of what happens when nobody did.
 *
 * ## Pasted and confirmed, never inferred
 *
 * ⛔ **DECISION 1083 APPLIES VERBATIM AND IT IS THE REASON THIS IS NOT ONE LINE
 * IN A SETTINGS FORM.** That decision refuses to infer a Search Console property
 * from a business's website *because a business whose site is a page on a
 * franchisor's domain would be silently mapped to the franchisor's whole
 * property*. Row 9 is that hazard with a write on the end of it: the address this
 * column holds is the address a later slice publishes pages to, injects markup
 * into and rolls back. Getting it wrong actuates against somebody who is not our
 * customer.
 *
 * ⚠️ **SO THE CONFIRMATION IS A REQUIRED ARGUMENT TYPED `true`, NOT A CHECK**
 * (220). A caller holding a plain `bool` cannot call this method at all: they
 * have to narrow it first, which is the moment they must actually check.
 * `if ($confirmed)` inside would be a line somebody can delete with no test
 * necessarily noticing; this fails Larastan and throws a `TypeError` from PHP,
 * with no code of ours involved. `locations_website_url_and_confirmation_travel_together`
 * is the same rule stated to a repair script.
 *
 * ## What the owner confirms is the normalised address, not what they typed
 *
 * {@see self::normalise()} is public and pure, and the screen renders its output
 * back to the owner before asking them to confirm. That ordering is the point: a
 * tracking query string quietly stripped after a confirmation is a value the
 * owner never agreed to, and a value the owner never agreed to is exactly what
 * 1083 refuses. `confirm()` normalises again regardless of what the screen did,
 * because a guard that only runs on the screen's path is 398's unfalsifiable
 * guard.
 *
 * ⚠️ **CHANGING THE ADDRESS CLEARS EVERY DETECTION FACT, IN THE SAME
 * TRANSACTION.** A scan of the old website says nothing about the new one, and
 * `ActuationTiers` would otherwise report a tier derived from a site this tenant
 * no longer runs — which is `messaging_lane`'s drift arriving through the back
 * door of the very columns built to prevent it.
 */
final class LocationWebsite
{
    /**
     * Host labels that name a machine rather than a website.
     *
     * @var list<string>
     */
    private const array REFUSED_HOSTS = ['localhost', 'localhost.localdomain'];

    /**
     * The one refusal sentence used for two different malformed shapes, so an
     * owner is not told two different things about the same mistake.
     */
    private const string NOT_AN_ADDRESS = 'That does not look like a web address. It should start '
        .'with https:// and name a website.';

    public function __construct(
        private readonly AuditService $audit,
        private readonly ShortLinks $shortLinks,
    ) {}

    /**
     * Record the website this tenant has said is theirs.
     *
     * @param  string  $pastedUrl  What the owner typed, before normalisation.
     * @param  string  $actor  An actor label — `user:14`, never a bare id.
     * @param  true  $confirmed  The owner said "yes, that is our website".
     *
     * @throws InvalidArgumentException when the address is not one this platform
     *                                  could act on
     */
    public function confirm(Location $location, string $pastedUrl, string $actor, true $confirmed): Location
    {
        $this->assertBelongsToTenant($location);

        $url = self::normalise($pastedUrl);

        $this->assertNotOurs($url);

        $before = $location->website_url;

        DB::transaction(function () use ($location, $url, $before, $actor, $pastedUrl): void {
            $location->forceFill(
                ['website_url' => $url, 'website_confirmed_at' => now()]
                // ⚠️ THE FACTS GO WITH THE ADDRESS THEY WERE ABOUT. Re-confirming
                // the same address keeps them, because nothing observed has
                // changed and clearing them would blank a tier reading every
                // time somebody pressed the button twice.
                + ($before === $url ? [] : [
                    'website_scanned_at' => null,
                    'wordpress_detected_at' => null,
                    'cloudflare_detected_at' => null,
                    // ⛔ **AND THE ABOUT PAGE GOES WITH THEM** (5742). It is the
                    // page rule 36's byline links to, and 5720 requires it be on
                    // *their own site* — so an About address confirmed against
                    // the previous website is, after this line, a byline
                    // pointing at somebody else's domain on every page we
                    // publish. Clearing it costs the owner one paste; keeping it
                    // costs them the thing rule 36 is for.
                    'about_url' => null,
                    'about_url_confirmed_at' => null,
                ])
            )->save();

            // ⚠️ BOTH SIDES, AND THE RAW PASTE AS WELL. `AuditService`'s own rule
            // is that an entry recording only the new value cannot answer what
            // happened — and the paste is what answers the *other* question a
            // wrongly-actuated site raises, which is whether we normalised an
            // owner's answer into somebody else's address.
            $this->audit->recordChange(
                'location.website_confirmed',
                $actor,
                ['website_url' => $before],
                ['website_url' => $url, 'pasted' => trim($pastedUrl)],
                $location,
            );
        });

        // ⚠️ DISPATCHED HERE RATHER THAN FROM THE SCREEN, ON `PlaceConfirmation`'s
        // reasoning: a committed address whose detection facts nobody refreshed
        // is a tenant who pasted their website and got a tier reading about the
        // site they just replaced. `afterCommit()` because the job re-reads the
        // row and would otherwise race the transaction above.
        ProbeLocationSiteJob::dispatch($location->business_id, (int) $location->id)->afterCommit();

        return $location;
    }

    /**
     * Record the page this tenant's byline points at — `29` §2 rule 36.
     *
     * ⛔ **THIS IS A GATE'S INPUT AND NOT A PREFERENCE** (5720, 5729). *"Auto-
     * published content carries a real author byline linked to a genuine About
     * page"*, and the owner's ruling is that **no About page means no publish**.
     * So the value written here is what decides whether anything this platform
     * generates ever reaches a website at all, and it is pasted and confirmed on
     * exactly {@see self::confirm()}'s terms rather than derived.
     *
     * ⛔ **NOTHING GUESSES `/about`, AND THAT IS THE WHOLE REASON THIS METHOD
     * EXISTS.** A derived address that happens to answer `200` — a franchisor's
     * page, a parked domain, a catch-all route — would pass rule 36's link check
     * while naming a company that is not the publisher. Decision 1083's
     * franchisor case, with a byline on it.
     *
     * ⚠️ **THE HOST MUST BE THE ONE THEY ALREADY CONFIRMED.** 5720's words are
     * *the About page **on their own site***, and a byline pointing off-site is
     * an attribution to somebody else. The comparison is exact apart from case,
     * matching {@see self::hostOf()} and the CHECK's own reasoning: `www.` is a
     * different host to WordPress and to us.
     *
     * ⛔ **AND NOTHING HERE FETCHES ANYTHING** (5743). *Does the link resolve* is
     * asked at publish time by `App\Services\Content\AuthorByline`, in a queued
     * job, because it is a question about today rather than about the day
     * somebody pasted it: a page that 404s six weeks later must stop the byline,
     * and a check made only here could never see that. Two checks at two times
     * would be 398's shape; one check, at the time that matters, is not.
     *
     * @param  string  $pastedUrl  What the owner typed, before normalisation.
     * @param  string  $actor  An actor label — `user:14`, never a bare id.
     * @param  true  $confirmed  The owner said "yes, that page is about us".
     *
     * @throws InvalidArgumentException when the address is not one a byline
     *                                  could honestly point at
     */
    public function confirmAbout(Location $location, string $pastedUrl, string $actor, true $confirmed): Location
    {
        $this->assertBelongsToTenant($location);

        $website = $location->website_url;

        if ($website === null || $location->website_confirmed_at === null) {
            throw new InvalidArgumentException(
                'Tell us your website address first, then the page on it that says who you are.'
            );
        }

        $url = self::normalise($pastedUrl);

        if (self::hostOf($url) !== self::hostOf($website)) {
            throw new InvalidArgumentException(
                'That page is on a different website. Paste the page on your own site that says '
                .'who you are — the one your customers can open.'
            );
        }

        $before = $location->about_url;

        DB::transaction(function () use ($location, $url, $before, $actor, $pastedUrl): void {
            $location->forceFill([
                'about_url' => $url,
                'about_url_confirmed_at' => now(),
            ])->save();

            $this->audit->recordChange(
                'location.about_page_confirmed',
                $actor,
                ['about_url' => $before],
                ['about_url' => $url, 'pasted' => trim($pastedUrl)],
                $location,
            );
        });

        return $location;
    }

    /**
     * The address we will act on, derived from what somebody typed.
     *
     * ⚠️ **PURE AND PUBLIC SO THE SCREEN CAN SHOW ITS ANSWER BEFORE ASKING FOR A
     * CONFIRMATION.** Everything it drops — the fragment, a tracking query
     * string, a default port, a trailing slash — is dropped in front of the
     * person confirming rather than after them.
     *
     * @throws InvalidArgumentException
     */
    public static function normalise(string $pastedUrl): string
    {
        $trimmed = trim($pastedUrl);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Paste the web address of your website.');
        }

        $parts = parse_url($trimmed);

        if ($parts === false) {
            throw new InvalidArgumentException(self::NOT_AN_ADDRESS);
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'A website address has to start with https:// — paste the whole thing, the way it '
                .'appears in your browser.'
            );
        }

        // ⛔ CREDENTIALS IN AN ADDRESS ARE REFUSED RATHER THAN STRIPPED. A
        // `https://user:pass@example.com` is a password somebody pasted into a
        // form, and the honest response is to refuse it and say so — stripping
        // it silently would store the address and leave the password in an audit
        // entry as part of the raw paste.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException(
                'That address has a username or password in it. Paste the plain address of your '
                .'website — the one anybody can open.'
            );
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host === '' || in_array($host, self::REFUSED_HOSTS, true)) {
            throw new InvalidArgumentException(self::NOT_AN_ADDRESS);
        }

        // A bare IP names a machine and not a website: it carries no domain to
        // match a pixel sighting against, no certificate anybody typed, and no
        // way to tell a tenant's own origin from a shared host.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.')) {
            throw new InvalidArgumentException(
                'That is not a website address. Paste the address your customers use, like '
                .'https://yourbusiness.com.'
            );
        }

        // The path survives, because a business can genuinely live at a page on a
        // larger site — which is 1083's franchisor case, and the reason this is
        // confirmed by a person rather than derived.
        $path = rtrim($parts['path'] ?? '', '/');

        // ⚠️ THE PORT IS KEPT WHEN IT IS NOT THE DEFAULT. Dropping it would
        // silently point every later fetch at a different server on the same
        // host; keeping it is not an endorsement, and a real business site on a
        // non-standard port is unusual enough that seeing it in the confirmation
        // is the useful outcome.
        $port = $parts['port'] ?? null;
        $default = $scheme === 'https' ? 443 : 80;
        $suffix = $port === null || $port === $default ? '' : ':'.$port;

        return $scheme.'://'.$host.$suffix.$path;
    }

    /**
     * The host of a stored address, for callers matching sightings against it.
     */
    public static function hostOf(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    /**
     * ⛔ **OUR OWN ADDRESSES ARE REFUSED, AND THE LIST IS READ RATHER THAN
     * TYPED.** A tenant who pastes the marketing site or a tracking domain would
     * have a later slice publish pages to it. The short-link domain is a registry
     * value (5512's rule: the domain moves, the code names the rule), so an unset
     * one is not a reason to refuse the whole paste — it means there is no such
     * domain to collide with yet.
     */
    private function assertNotOurs(string $url): void
    {
        $host = self::hostOf($url);

        $ours = array_filter([
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            $this->shortLinkDomain(),
        ]);

        foreach ($ours as $mine) {
            if ($host === $mine || str_ends_with($host, '.'.$mine)) {
                throw new InvalidArgumentException(
                    'That is one of our own addresses rather than your website. Paste the address '
                    .'your customers use to find you.'
                );
            }
        }
    }

    private function shortLinkDomain(): ?string
    {
        try {
            return strtolower($this->shortLinks->domain());
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * The wrong-tenant refusal — the case RLS cannot catch once a model is in
     * hand, refused here for {@see LocationTimezone}'s reason.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. This address is the one a later slice '
            .'writes to, so confirming it here would point our actuation at somebody else\'s site.',
        );
    }
}
