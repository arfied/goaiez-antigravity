<?php

declare(strict_types=1);

namespace App\Services\Fetch;

use App\Contracts\FetchGateway;
use App\Enums\FetchOutcome;
use App\Enums\FetchRefusalReason;
use App\Enums\FetchTier;
use App\Enums\RobotsVerdict;
use App\Models\FetchAttempt;
use App\Models\FetchSource;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The F0 gateway: one direct request, honest identity, policy first.
 *
 * `BUILD-PLAN` §2.5.2 slice D — "the interface plus `fetch_sources` and
 * `fetch_attempts` · honest UA, robots respected, per-source rate budget ·
 * `guided_only` ceiling seeded, Google seeded never-fetchable. **F1–F3
 * deliberately not built**".
 *
 * THE ORDER OF THE GATES IS THE DESIGN. Policy is checked before the network,
 * always, and each gate is cheaper than the one after it:
 *
 *   1. source exists           unknown source is a programming error, not a refusal
 *   2. kill switch             one boolean read
 *   3. method ceiling          `guided_only` dies here, at every tier (`40` §6.2)
 *   4. tier implemented        F1/F2 raise rather than silently degrading
 *   5. cool-down               a blocked source waits before we knock again
 *   6. rate budget             our own politeness, counted
 *   7. robots.txt              one cached request, and it fails closed in
 *                                two different ways — their rule, or nothing
 *                                established at all (`FetchRefusalReason`)
 *   8. the fetch
 *
 * A refusal at 2–7 opens no socket. That is the property the `guided_only` test
 * asserts, and it is why the check is here rather than in an adapter: `40` §6.2
 * requires it "enforced in the gateway (build-failing test), not in adapter
 * etiquette".
 *
 * NOT spatie/crawler, which `40` §6.1 names for F0. That package is a crawler —
 * queues, depth limits, a robots parser, a browser bridge — and F0 is one
 * request. CLAUDE.md requires approval before a dependency, and this needed no
 * argument to avoid: Laravel's HTTP client does it, and it keeps Http::fake()
 * working, which is what makes the no-socket assertions above testable at all.
 * Revisit if F1 lands and brings Browsershot with it.
 */
final class DirectFetchGateway implements FetchGateway
{
    /**
     * `40` §6.1's cool-down ladder seed: 6h, then 24h, then 72h per source.
     *
     * "Most 'blocks' are rate limits that patience fixes for free."
     *
     * @var list<int>
     */
    public const array COOLDOWN_HOURS = [6, 24, 72];

    /**
     * A response larger than this is not a page we need. Caps memory on a path
     * that fetches URLs a stranger chose.
     */
    private const int MAX_BYTES = 2_000_000;

    /**
     * The response headers a `FetchResult` carries back, and nothing else (5545).
     *
     * ⛔ **AN ALLOWLIST, NOT A CONVENIENCE.** A response from somebody else's
     * website carries `set-cookie` and whatever else that origin felt like
     * sending, and a result object is a thing that ends up in a queue payload and
     * an exception message. Keeping four names means the other twenty cannot.
     *
     *   server        `cloudflare` is how a proxied origin announces itself, and
     *                 the actuation tier scanner reads it (`41` Part 5, T2)
     *   cf-ray        present on every Cloudflare-proxied response and on no
     *                 other, so the two together are a fact rather than a guess
     *   link          WordPress advertises its REST root here —
     *                 `<https://site/wp-json/>; rel="https://api.w.org/"` — which
     *                 is the one WordPress signal that survives a themed site
     *                 with no `/wp-content/` paths in its markup
     *   x-powered-by  the platform an origin names for itself
     *
     * @var list<string>
     */
    public const array RECORDED_HEADERS = ['server', 'cf-ray', 'link', 'x-powered-by'];

    public function __construct(
        private readonly RobotsPolicy $robots,
    ) {}

    private function cooldownHours(): array
    {
        return app(DefaultsRegistry::class)->intList('fetch.cooldown_hours');
    }

    public function permits(string $sourceKey, FetchTier $tier = FetchTier::F0): bool
    {
        $source = $this->source($sourceKey);

        return $source->permits($tier) && $tier->isImplemented();
    }

    /**
     * Guzzle options that route a source's requests through the operator's
     * proxy — only the tenant's own website, only when the credential is set.
     *
     * @return array{proxy?: string}
     */
    public static function proxyOptionsFor(string $sourceKey): array
    {
        if ($sourceKey !== 'tenant_site' || ! PlatformCredentials::has('fetch_proxy_url')) {
            return [];
        }

        return ['proxy' => PlatformCredentials::get('fetch_proxy_url')];
    }

    public function fetch(string $sourceKey, string $url, FetchTier $tier = FetchTier::F0): FetchResult
    {
        $source = $this->source($sourceKey);

        if (! $tier->isImplemented()) {
            // A programming error, not a policy outcome. Silently serving F0
            // when F1 was asked for would make "this page needs JS" and "this
            // page is empty" indistinguishable, which `40` §6.4 turns into two
            // different things the owner is told.
            throw new RuntimeException(
                "Fetch tier [{$tier->value}] is not implemented. Only F0 ships in row 2 slice D; "
                .'the F1-F3 ladder belongs to the row that needs it (BUILD-PLAN §2.5.2).'
            );
        }

        if ($source->kill) {
            return $this->refuse($source, $url, $tier, FetchRefusalReason::KillSwitch);
        }

        if (! $source->method_ceiling->permitsFetching()) {
            // `40` §6.2: guided_only sources are "ineligible for any fetch tier
            // including F2 — permanently". No socket, ever.
            return $this->refuse($source, $url, $tier, FetchRefusalReason::GuidedOnly);
        }

        if (! $source->method_ceiling->permits($tier)) {
            return $this->refuse($source, $url, $tier, FetchRefusalReason::AboveCeiling);
        }

        if ($this->coolingDown($source)) {
            return $this->refuse($source, $url, $tier, FetchRefusalReason::CoolingDown);
        }

        if ($this->overRateBudget($source)) {
            return $this->refuse($source, $url, $tier, FetchRefusalReason::RateBudget);
        }

        $robots = $this->robots->verdict($url);

        if (! $robots->permitsFetching()) {
            // ⛔ **WHICH REFUSAL THIS IS DECIDES WHAT AN OWNER IS TOLD ABOUT
            // THEIR OWN WEBSITE** (9480-9499). Both arms open no socket; only
            // the first is a rule the origin published, and
            // `BylineCheck::$ownRobotsRefused` carries that claim to a screen
            // naming a file on their server.
            return $this->refuse($source, $url, $tier, $robots === RobotsVerdict::Disallowed
                ? FetchRefusalReason::RobotsDisallow
                : FetchRefusalReason::RobotsUnavailable);
        }

        return $this->perform($source, $url, $tier);
    }

    private function perform(FetchSource $source, string $url, FetchTier $tier): FetchResult
    {
        try {
            $response = VendorLog::timed(
                'fetch:'.$source->key,
                'GET',
                $url,
                fn () => Http::withHeaders(['User-Agent' => RobotsPolicy::USER_AGENT])
                    ->timeout((int) config('fetch.timeout', 10))
                    // Redirects are followed but capped: a redirect chain is a
                    // cheap way to make one permitted fetch into many.
                    ->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true]] + self::proxyOptionsFor($source->key))
                    ->get($url),
            );
        } catch (ConnectionException) {
            VendorLog::failure('fetch:'.$source->key, 'GET', $url, ConnectionException::class);

            $this->record($source, $url, $tier, FetchOutcome::Error);

            return FetchResult::failed(FetchOutcome::Error, $tier);
        }

        $status = $response->status();
        $body = $response->body();

        $outcome = match (true) {
            $status === 403, $status === 429 => FetchOutcome::Blocked,
            $status >= 400 => FetchOutcome::Error,
            strlen($body) > self::MAX_BYTES => FetchOutcome::Error,
            trim($body) === '' => FetchOutcome::Empty,
            $this->looksLikeChallenge($body) => FetchOutcome::Challenge,
            default => FetchOutcome::Ok,
        };

        $this->record($source, $url, $tier, $outcome, $status);

        return $outcome === FetchOutcome::Ok
            ? FetchResult::ok($tier, $body, $status, $this->recordedHeaders($response))
            : FetchResult::failed($outcome, $tier, $status);
    }

    /**
     * The allowlisted headers of a response, lower-cased and flattened.
     *
     * ⚠️ **ONLY ON `Ok`, WHICH IS THE CALLER'S DOING AND IS DELIBERATE.** A
     * challenge page and a 403 both carry `server: cloudflare` — from the
     * *challenge*, not from the origin — so handing those headers back would let
     * a caller record "this site is behind Cloudflare" on the strength of a
     * response that never reached the site.
     *
     * @return array<string, string>
     */
    private function recordedHeaders(Response $response): array
    {
        $kept = [];

        foreach (self::RECORDED_HEADERS as $name) {
            $value = $response->header($name);

            if ($value !== '') {
                $kept[$name] = $value;
            }
        }

        return $kept;
    }

    /**
     * A 200 that is a bot check rather than the page.
     *
     * Deliberately conservative: a false positive costs one honest "we could not
     * read this", while a false negative feeds an interstitial into a NAP parser
     * and reports a business's address as whatever the challenge page said.
     */
    private function looksLikeChallenge(string $body): bool
    {
        if (strlen($body) > 64_000) {
            return false;
        }

        $haystack = strtolower(substr($body, 0, 8_000));

        foreach (['just a moment', 'checking your browser', 'cf-browser-verification', 'captcha'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the source is inside a cool-down from an earlier block.
     */
    private function coolingDown(FetchSource $source): bool
    {
        return FetchAttempt::query()
            ->where('source_key', $source->key)
            ->coolingDown()
            ->exists();
    }

    /**
     * Our own politeness budget, counted from the ledger.
     *
     * Refusals are excluded: a request we declined to make did not consume the
     * remote host's patience, and counting it would let a run of policy
     * refusals lock out the fetches that are actually permitted.
     */
    private function overRateBudget(FetchSource $source): bool
    {
        $perMinute = $source->budgetPerMinute();
        $perDay = $source->budgetPerDay();

        if ($perMinute === null && $perDay === null) {
            return false;
        }

        $base = FetchAttempt::query()
            ->where('source_key', $source->key)
            ->where('outcome', '!=', FetchOutcome::Refused->value);

        if ($perMinute !== null
            && (clone $base)->where('created_at', '>=', Carbon::now()->subMinute())->count() >= $perMinute) {
            return true;
        }

        return $perDay !== null
            && (clone $base)->where('created_at', '>=', Carbon::now()->subDay())->count() >= $perDay;
    }

    private function refuse(FetchSource $source, string $url, FetchTier $tier, FetchRefusalReason $reason): FetchResult
    {
        $this->record($source, $url, $tier, FetchOutcome::Refused);

        return FetchResult::refused($tier, $reason);
    }

    private function record(
        FetchSource $source,
        string $url,
        FetchTier $tier,
        FetchOutcome $outcome,
        ?int $status = null,
    ): void {
        FetchAttempt::query()->create([
            'source_key' => $source->key,
            // Hashed, never stored: a fetched URL can carry a business name or
            // a pasted query string, and this table has no tenant.
            'url_hash' => hash('sha256', $url),
            'tier' => $tier,
            'outcome' => $outcome,
            'http_status' => $status,
            'cooldown_until' => $outcome->triggersCooldown()
                ? Carbon::now()->addHours($this->nextCooldownHours($source))
                : null,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * 6h, then 24h, then 72h — escalating with how many times this source has
     * already been cooled down recently, per `40` §6.1's seed.
     */
    private function nextCooldownHours(FetchSource $source): int
    {
        $recent = FetchAttempt::query()
            ->where('source_key', $source->key)
            ->whereNotNull('cooldown_until')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return $this->cooldownHours()[min($recent, count($this->cooldownHours()) - 1)];
    }

    /**
     * An unregistered source is a programming error, not a refusal.
     *
     * Failing loudly here is what makes the import lint meaningful: code that
     * routes a fetch through the gateway without registering its policy has not
     * actually accepted the policy, and a silent default would let it think it
     * had.
     */
    private function source(string $key): FetchSource
    {
        $source = FetchSource::query()->find($key);

        if ($source === null) {
            throw new RuntimeException(
                "Unknown fetch source [{$key}]. Every fetch needs a fetch_sources row carrying its "
                .'method ceiling — see docs/40 §6.3 and the slice D migration.'
            );
        }

        return $source;
    }
}
