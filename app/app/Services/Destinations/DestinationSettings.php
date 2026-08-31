<?php

declare(strict_types=1);

namespace App\Services\Destinations;

use App\Enums\ReviewDestination;
use App\Models\Location;
use App\Models\ReviewDestinationSetting;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of `review_destinations`.
 *
 * THE SAME THREE RULES ARE ENFORCED IN THREE PLACES ON PURPOSE, because each
 * place catches what the others cannot:
 *
 *   Yelp is never a destination     enum: no case exists       db: no valid value
 *   Trustpilot's threshold is 0     enum: forcedThreshold()    db: CHECK
 *   An enabled destination has a    enum: linkIsDerived()      db: CHECK
 *   link
 *
 * This class is the middle column, and what it adds over the other two is an
 * error a person can act on. A CHECK violation surfaces as a SQLSTATE naming a
 * constraint; "Trustpilot's terms require every customer be invited, so this
 * destination is disabled rather than gated" tells the caller what to do instead.
 *
 * WHAT THIS DELIBERATELY COSTS. Trustpilot's threshold is not a setting. A
 * tenant who does not want to invite every customer disables Trustpilot; they
 * cannot gate it (`17` FPR-04b: "the destination is disabled, not gated"). That
 * will read as a missing feature to anybody who has not read this, which is why
 * it is written here as well as in the enum.
 *
 * NOTHING HERE APPLIES A THRESHOLD TO A CUSTOMER. This is the configuration
 * layer only; `ReviewRouter` owns application. ⚠️ **It used to owe a second
 * condition and no longer does**: application was refused entirely while
 * `autopilot_settings.gating_ack_at` was null, which was every tenant (290), so
 * a threshold written here reached nobody. Decisions 2074 and 2660 removed the
 * acknowledgement and the column, so what this class writes is what applies.
 * Do not add the comparison here — one place decides who is invited.
 */
final class DestinationSettings
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly DefaultsRegistry $registry,
    ) {}

    /**
     * The threshold a destination starts at, before any tenant has chosen.
     *
     * ⚠️ **THREE SOURCES, IN ORDER, AND WHICH ONE ANSWERS IS THE WHOLE
     * DISTINCTION.** A platform's own condition of use wins outright and is not
     * ours or the tenant's to move — `forcedThreshold()`, and Trustpilot's 0 is
     * the only one today. Then a starting point that platform's own *risk*
     * dictates — `defaultThreshold()`, and Yelp's 5 is the only one, because
     * 1145's penalty makes sending fewer people there the conservative reading
     * of a ruling that named no number. Only then our general preference, which
     * lives in the registry so that moving it is a settings row rather than a
     * deploy (1142), and so that `38` Part 2's rule against threshold literals
     * holds.
     *
     * ⚠️ **THE ORDER IS LOAD-BEARING AT THE SECOND STEP.** An operator moving
     * `reviews.default_invite_threshold` in Ops is expressing a preference about
     * Google and Facebook; letting it move Yelp too would quietly widen exposure
     * to the one platform that publishes a penalty for asking at all.
     *
     * ⚠️ **THIS IS THE PLATFORM DEFAULT, NOT THE TENANT'S SETTING.** It seeds a
     * row and pre-selects a screen. A tenant who has chosen through
     * `ReviewGating` has a stored number, and nothing here reads back over it —
     * `seedDefaults()` is idempotent and leaves an existing row alone precisely
     * so that an operator moving the registry key cannot reset somebody's
     * config underneath them.
     */
    public function defaultThresholdFor(ReviewDestination $destination): int
    {
        $platformRule = $destination->forcedThreshold() ?? $destination->defaultThreshold();

        if ($platformRule !== null) {
            return $platformRule;
        }

        $default = $this->registry->int('reviews.default_invite_threshold');

        // ⚠️ 0 AND 1 ARE REFUSED HERE RATHER THAN SEEDED, and the reason is not
        // that they are invalid ratings — `setThreshold()` accepts both. It is
        // that both mean "invite everybody", which is a *tenant's* answer with
        // its own branch through `ReviewGating::inviteEveryone()`: no threshold
        // in force and, deliberately, no acknowledgement on file (290). Seeding
        // it as the platform default would put every new location in that state
        // without anybody choosing it, and `ReviewGating::chosenThreshold()`
        // would read it back as a choice the owner made. Decision 502's
        // posture: refuse the figure rather than quietly accept one that means
        // something else.
        if ($default < 2 || $default > 5) {
            throw new InvalidArgumentException(
                'The platform default invite threshold is '.$default.', and it must be '
                .'between 2 and 5. Below that it means "invite everybody", which is a '
                .'tenant\'s own answer recorded through ReviewGating rather than a default '
                .'a location can be seeded into — see decision 1187. Set '
                .'`reviews.default_invite_threshold` back in Ops.',
            );
        }

        return $default;
    }

    /**
     * This location's stored threshold for one destination, or null with no row.
     *
     * A read rather than a write, and it is here because the lint that holds
     * `review_destinations` to this service holds *reads* too — `ReviewGating`
     * needs to answer "what did this owner choose" and may not query the model
     * itself. Decision 624's rule applied at the point it was written for: the
     * caller belongs behind the service, not on the allowlist.
     */
    public function thresholdFor(Location $location, ReviewDestination $destination): ?int
    {
        $setting = ReviewDestinationSetting::query()
            ->where('location_id', $location->id)
            ->where('destination', $destination)
            ->first();

        return $setting instanceof ReviewDestinationSetting
            ? (int) $setting->invite_threshold
            : null;
    }

    /**
     * Seed one location's seedable destination rows, all disabled.
     *
     * PROVISIONING SEEDS, IT DOES NOT ENABLE (decision 312). Slice H's
     * provisioner creates a location with no `google_place_id` — the id is
     * resolved later, when the owner pastes their listing, and PlaceConfirmation
     * is the only thing that writes it. So "provisioning enables Google" and
     * "enabling Google without a place_id throws" cannot both be true, and the
     * second is the one worth keeping: a Google row enabled with no id is a
     * broken invite that looks configured.
     *
     * ⚠️ **YELP IS NOT SEEDED, AND THIS LOOP IS WHERE THE NEW BUILD-FAILING RULE
     * IS ACTUALLY ENFORCED (1161).** Iterating `cases()` was correct while no
     * Yelp case existed; adding one at 1160 would have silently given every
     * location on every tenant a Yelp row at provisioning — which is precisely
     * *"by seed, default, or provisioning"*, the thing the replaced test
     * forbids. The reversal's most dangerous line is the one nobody had to
     * write. **A disabled row is not harmless here**: it is the row an admin
     * screen lists, and a tenant who has never heard of 1145's penalty finds
     * Yelp already present and one toggle away.
     *
     * `enable()` upserts (309), so Yelp's row appears the first time somebody
     * deliberately turns it on with a confirmed link — which is the whole of the
     * confirmed-listing path, and needs no backfill.
     *
     * Idempotent. Existing rows are left exactly as they are, including their
     * thresholds — re-running this must never quietly reset somebody's config.
     */
    public function seedDefaults(Location $location): void
    {
        $this->assertBelongsToTenant($location);

        foreach (ReviewDestination::cases() as $destination) {
            if (! $destination->isSeededAtProvisioning()) {
                continue;
            }

            $this->rowFor($location, $destination);
        }
    }

    /**
     * Turn a destination on, creating its row if there is not one yet.
     *
     * UPSERTS, DELIBERATELY (decision 309). A destination added to the enum
     * later then needs no backfill migration walking every location of every
     * tenant — the row appears the first time somebody enables it.
     *
     * @param  ?string  $linkUrl  Required for Facebook and Trustpilot, and
     *                            refused for Google, whose link is derived.
     * @param  string  $actor  An actor label — 'user:14', never a bare id.
     *
     * @throws InvalidArgumentException
     */
    public function enable(
        Location $location,
        ReviewDestination $destination,
        ?string $linkUrl,
        string $actor,
    ): ReviewDestinationSetting {
        $this->assertBelongsToTenant($location);

        if ($destination->linkIsDerived()) {
            if ($linkUrl !== null) {
                throw new InvalidArgumentException(
                    $destination->label().'\'s review link is derived from the location\'s '
                    .'place id, never stored. Storing a copy would be a second source of '
                    .'truth that silently stops matching the first time a listing merge '
                    .'changes the place id.',
                );
            }

            if (trim((string) $location->google_place_id) === '') {
                throw new InvalidArgumentException(
                    'This location has no confirmed Google place id, so there is nothing to '
                    .'derive a review link from. Resolve and confirm the listing first — '
                    .'enabling now would produce a broken invite at the one moment a '
                    .'customer is willing to act.',
                );
            }
        } else {
            $linkUrl = $this->assertUsableLink($destination, $linkUrl);
        }

        return DB::transaction(function () use ($location, $destination, $linkUrl, $actor): ReviewDestinationSetting {
            // ⚠️ THE LINK IS PASSED INTO rowFor() RATHER THAN SET AFTER IT,
            // BECAUSE A YELP ROW MAY NOT EXIST WITHOUT ONE EVEN FOR AN INSTANT.
            // `review_destinations_yelp_is_never_seeded` is a CHECK, so the
            // intermediate `link_url = null` insert this method used to do
            // violates it inside the transaction — the legitimate path would
            // fail with the same SQLSTATE as a seeder. Creating the row with its
            // link is also the more honest statement of 1162: for Yelp the row
            // and the confirmed link are one act, not two.
            $setting = $this->rowFor($location, $destination, $linkUrl);

            $setting->forceFill([
                'enabled' => true,
                'link_url' => $linkUrl,
            ])->save();

            // The link is in the metadata because "which URL did this tenant
            // point their Trustpilot button at" is precisely the question an
            // investigation asks, and it is not personal data. Google's is
            // absent because there is nothing to record.
            $this->audit->record('destination.enabled', $actor, $setting, [
                'destination' => $destination->value,
                'location_id' => $location->id,
                'link_url' => $linkUrl,
            ]);

            return $setting;
        });
    }

    /**
     * Turn a destination off. The row and its threshold survive, so turning it
     * back on does not silently reset a number somebody chose.
     */
    public function disable(Location $location, ReviewDestination $destination, string $actor): void
    {
        $this->assertBelongsToTenant($location);

        DB::transaction(function () use ($location, $destination, $actor): void {
            $setting = $this->rowFor($location, $destination);

            // The link survives the disable (the row and its threshold are
            // read before the row is touched) — recorded here for the same
            // reason enable() records it: "what was this button pointing at
            // when it was switched off" is the same investigative question,
            // and an entry that only ever names the destination cannot answer
            // it once the link is later changed or the destination re-enabled
            // against a different URL.
            $linkUrl = $setting->link_url;

            $setting->forceFill(['enabled' => false])->save();

            $this->audit->record('destination.disabled', $actor, $setting, [
                'destination' => $destination->value,
                'location_id' => $location->id,
                'link_url' => $linkUrl,
            ]);
        });
    }

    /**
     * Set the rating at or above which a customer is invited to this destination.
     *
     * @throws InvalidArgumentException when the platform's own terms forbid the
     *                                  value, or when the value is not a rating.
     */
    public function setThreshold(
        Location $location,
        ReviewDestination $destination,
        int $threshold,
        string $actor,
    ): void {
        $this->assertBelongsToTenant($location);

        $forced = $destination->forcedThreshold();

        if ($forced !== null && $threshold !== $forced) {
            throw new InvalidArgumentException(
                $destination->label().'\'s own terms require that every customer be invited, '
                .'so its threshold is fixed at '.$forced.' and is not a setting. A business '
                .'that does not want to invite everybody disables this destination rather '
                .'than gating it — cherry-picking is a violation they enforce publicly, and '
                .'a flagged profile is not recoverable by changing this number back.',
            );
        }

        if ($threshold < 0 || $threshold > 5) {
            throw new InvalidArgumentException(
                'A review threshold is a star rating, so it runs from 0 to 5. A value '
                .'outside that range means the scale was guessed at, and the guess reads '
                .'as "invite nobody" with no error anywhere.',
            );
        }

        DB::transaction(function () use ($location, $destination, $threshold, $actor): void {
            $setting = $this->rowFor($location, $destination);

            $before = ['invite_threshold' => $setting->invite_threshold];

            $setting->forceFill(['invite_threshold' => $threshold])->save();

            // recordChange() rather than record(): `29` §19.3 requires threshold
            // changes carry old and new values by name, and an entry holding
            // only the new one cannot answer what happened. The destination
            // rides in the `after` half because a threshold with no destination
            // is the ambiguity this whole slice exists to remove.
            $this->audit->recordChange(
                'destination_threshold.changed',
                $actor,
                before: $before,
                after: ['invite_threshold' => $threshold, 'destination' => $destination->value],
                entity: $setting,
            );
        });
    }

    /**
     * The destinations this location currently offers, Google first.
     *
     * Sorted in PHP rather than SQL, because the order is the enum's declaration
     * order and expressing that as an ORDER BY means duplicating the list in a
     * CASE expression — a second source of truth for something the enum already
     * settles. It also keeps this clear of the NULLS-FIRST ordering lint.
     *
     * @return Collection<int, ReviewDestinationSetting>
     */
    public function offeredFor(Location $location): Collection
    {
        $order = array_flip(array_map(
            fn (ReviewDestination $d): string => $d->value,
            ReviewDestination::cases(),
        ));

        /** @var Collection<int, ReviewDestinationSetting> $offered */
        $offered = ReviewDestinationSetting::query()
            ->where('location_id', $location->id)
            ->where('enabled', true)
            ->get()
            ->sortBy(fn (ReviewDestinationSetting $s): int => $order[$s->destination->value])
            ->values();

        return $offered;
    }

    /**
     * Where this destination actually sends somebody.
     *
     * Google's is computed here rather than read from the row, which is the
     * whole of decision 308 in one branch.
     */
    public function linkFor(Location $location, ReviewDestinationSetting $setting): string
    {
        if ($setting->location_id !== $location->id) {
            throw new InvalidArgumentException(
                'That destination setting belongs to a different location. Both are '
                .'tenant-scoped, so this cannot cross a tenant boundary — but it can still '
                .'derive one location\'s Google review link (or return another location\'s '
                .'stored link) for a caller who passed a mismatched pair, which is a wrong '
                .'invite rather than a missing one.',
            );
        }

        if ($setting->destination->linkIsDerived()) {
            return GoogleReviewLink::forPlaceId((string) $location->google_place_id);
        }

        // THE READ PATH MUST FAIL CLOSED INDEPENDENTLY OF THE WRITE PATH,
        // because assertUsableLink() running only in enable() protects only
        // what enable() wrote. The database has other writers: a repair
        // script, a seeder, a future admin screen writing `link_url` directly.
        // The enabled-has-a-link CHECK guarantees this column is non-null; it
        // says nothing about the host, so a row written around the service
        // sails through it and would otherwise be handed straight back here —
        // a button carrying a platform's name, on a page served to somebody
        // else's customer, pointed wherever that write put it. Re-running the
        // same host and scheme validation on every read is what makes the
        // guarantee hold regardless of how the row got here.
        return $this->assertUsableLink($setting->destination, $setting->link_url);
    }

    /**
     * Fetch this location's row for a destination, creating a disabled default
     * if there is not one.
     *
     * forceFill() rather than create(): `destination` and `invite_threshold` are
     * guarded on the model precisely so that nothing else can set them, and this
     * service is the "else" the guard exempts. firstOrCreate() would silently
     * drop both — $guarded applies to the merged attribute array — and leave a
     * row with a null destination.
     *
     * @param  ?string  $linkUrl  Written only when the row is created, and only
     *                            by enable(), which has already validated it.
     *                            ⚠️ It is NOT applied to an existing row: this
     *                            method's other callers create disabled defaults
     *                            and re-reading a row must never rewrite a link
     *                            somebody chose.
     */
    private function rowFor(
        Location $location,
        ReviewDestination $destination,
        ?string $linkUrl = null,
    ): ReviewDestinationSetting {
        $existing = ReviewDestinationSetting::query()
            ->where('location_id', $location->id)
            ->where('destination', $destination)
            ->first();

        if ($existing instanceof ReviewDestinationSetting) {
            return $existing;
        }

        $setting = new ReviewDestinationSetting;

        $setting->forceFill([
            'location_id' => $location->id,
            'destination' => $destination,
            'enabled' => false,
            'invite_threshold' => $this->defaultThresholdFor($destination),
            'link_url' => $linkUrl,
        ])->save();

        return $setting;
    }

    /**
     * A tenant-supplied link that actually goes where the button says it does.
     *
     * PARSE, THEN MATCH THE HOST — never match a pattern against the whole URL,
     * which is how `https://evil.test/?x=www.trustpilot.com` gets through. The
     * same reasoning as GoogleLinkHosts, which row 2 slice C built for the
     * inbound direction; this is the outbound one, and the damage is different
     * in kind: a button labelled "Trustpilot" on a page we serve to somebody
     * else's customer.
     *
     * @throws InvalidArgumentException
     */
    private function assertUsableLink(ReviewDestination $destination, ?string $linkUrl): string
    {
        $linkUrl = trim((string) $linkUrl);

        if ($linkUrl === '') {
            throw new InvalidArgumentException(
                'An enabled '.$destination->label().' destination needs a link. A destination '
                .'with nowhere to send anybody is a dead button on a page a customer is '
                .'looking at because they were willing to leave a review.',
            );
        }

        // A BACKSLASH IS REFUSED OUTRIGHT, BEFORE PARSING, BECAUSE PHP AND THE
        // BROWSER DISAGREE ABOUT WHERE THE AUTHORITY ENDS. parse_url() follows
        // RFC 3986 and does not treat `\` as an authority terminator, so
        // `https://evil.test\@trustpilot.com/` parses to host `trustpilot.com`
        // (everything before the `@` is read as userinfo) and sails through
        // hostIsAllowed(). Every major browser follows the WHATWG URL spec
        // instead, which normalises `\` to `/` for special schemes like https —
        // so the same string, in a customer's address bar, terminates the
        // authority at the backslash and navigates to `evil.test`. That is a
        // parser differential, not a bug in either parser, and there is no
        // string of parse_url() calls that resolves it: the two specs
        // disagree on the input, so the only safe move is to refuse the
        // character rather than try to out-parse either of them. A review
        // destination link never legitimately contains one.
        if (str_contains($linkUrl, '\\')) {
            throw new InvalidArgumentException(
                'That link contains a backslash, which PHP and a customer\'s browser parse '
                .'differently — the two can disagree about which host the link actually '
                .'points at, which is exactly the gap a spoofed link would use.',
            );
        }

        $scheme = parse_url($linkUrl, PHP_URL_SCHEME);

        if (! is_string($scheme) || strtolower($scheme) !== 'https') {
            throw new InvalidArgumentException(
                'Review destination links must be https. This URL is sent to a customer\'s '
                .'browser from a page of ours.',
            );
        }

        $host = parse_url($linkUrl, PHP_URL_HOST);

        if (! is_string($host) || ! $this->hostIsAllowed($destination, $host)) {
            throw new InvalidArgumentException(
                'That link does not point at '.$destination->label().'. A button carrying a '
                .'platform\'s name has to go to that platform: the alternative is us '
                .'sending a tenant\'s customer somewhere arbitrary under a label they '
                .'trust.',
            );
        }

        return $linkUrl;
    }

    /**
     * Exact host match, or a dot-anchored suffix for entries beginning with `.`.
     *
     * NEVER A SUBSTRING. `nottrustpilot.com` and `trustpilot.com.evil.test` both
     * contain "trustpilot.com"; neither is Trustpilot. The dot anchor is what
     * makes `.trustpilot.com` cover `uk.trustpilot.com` without covering either
     * of those.
     */
    private function hostIsAllowed(ReviewDestination $destination, string $host): bool
    {
        $host = strtolower(rtrim(trim($host), '.'));

        if ($host === '') {
            return false;
        }

        foreach ($destination->allowedLinkHosts() as $allowed) {
            if (str_starts_with($allowed, '.')) {
                if (str_ends_with($host, $allowed)) {
                    return true;
                }

                continue;
            }

            if ($host === $allowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refuse to configure somebody else's location.
     *
     * Slice A's decision 301, in the same shape: the read path is scoped and the
     * write path is not. `business_id` comes from the ambient tenant while
     * `location_id` comes from the passed model, and `review_destinations`
     * carries a plain single-column foreign key whose integrity check bypasses
     * row security — so without this, tenant A could file destination config
     * naming tenant B's location. That is the *wrong tenant* case CLAUDE.md
     * warns RLS cannot catch, checked here where both ids are in hand.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. Destination config is written '
            .'against the acting business, so this would file settings under a business '
            .'that does not own the location they apply to.',
        );
    }
}
