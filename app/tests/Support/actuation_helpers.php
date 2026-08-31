<?php

declare(strict_types=1);

use App\Contracts\CmsAdapter;
use App\Contracts\SearchConsoleClient;
use App\Enums\ActuationTier;
use App\Enums\ConnectionStatus;
use App\Enums\DeviceClass;
use App\Enums\OauthProvider;
use App\Enums\WebVital;
use App\Models\Business;
use App\Models\GscSiteProperty;
use App\Models\Location;
use App\Models\OauthConnection;
use App\Models\SiteChange;
use App\Models\User;
use App\Models\WordPressCredential;
use App\Services\Actuation\ActuationActor;
use App\Services\Actuation\AdapterHealth;
use App\Services\Actuation\AdapterOutcome;
use App\Services\Actuation\ChangeSet;
use App\Services\Actuation\FieldSupport;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteSnapshot;
use App\Services\Actuation\SpeedDecider;
use App\Services\Gsc\SearchAnalyticsResult;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| The actuation chain's shared fixtures
|--------------------------------------------------------------------------
|
| ⛔ **HERE RATHER THAN IN A TEST FILE, BECAUSE A GLOBAL HELPER DECLARED IN ONE
| TEST FILE AND CALLED FROM ANOTHER WORKS ONLY BY LOAD ORDER** — `tests/Pest.php`
| says so beside `pixel_helpers.php`, for the same reason, and the actuation
| directory had already reached that shape: `speedAdapter()` lived in
| `SpeedFixesTest` and `SpeedDeciderTest` called it. **The tell is that running
| the calling file on its own fails with *"Call to undefined function"***, which
| is exactly what a lane trying to drive its own test red hits first (5960).
|
| ⚠️ **ONE HOME PER FIXTURE AND NO SECOND COPY.** 5753 and 5820 are two slices
| lost to a fake that answered something the live adapter does not; a second
| copy of one of these is a second thing to keep true, and the one that drifts
| is the one nobody is reading.
|
*/

/**
 * A location whose account points at the website its change sets are on.
 *
 * ⛔ **`LocationFactory` DELIBERATELY WRITES NO `website_url`** (5540, 6100), so
 * every fixture that opens a change set has to state the address itself — and
 * before 6143 nothing on the revert path read the column, so a location with a
 * null website and a change set at `https://ledgerplumbing.test/…` was an
 * ordinary, passing fixture describing a state production cannot reach.
 * `SiteChanges::revert()` now refuses a change set whose page is not on the
 * website its location names (5976), and this is what makes a test fixture
 * describe a tenant rather than a contradiction.
 *
 * ⚠️ **THE CONFIRMATION TRAVELS WITH THE URL**, because
 * `locations_website_url_and_confirmation_travel_together` makes a value without
 * one unrepresentable — the CHECK is what stops this helper inventing a state
 * the writer cannot produce.
 *
 * @param  array<string, mixed>  $attributes
 */
function actuationLocation(string $host = 'ledgerplumbing.test', array $attributes = []): Location
{
    $location = Location::factory()->create($attributes);

    $location->forceFill([
        'website_url' => 'https://'.$host,
        'website_confirmed_at' => now(),
    ])->save();

    return $location->refresh();
}

/**
 * A tenant, an owner and a location whose website we may act on.
 *
 * Named for this file: Pest loads every test file's functions into one global
 * namespace (694, 808).
 */
function measurementTenant(string $name = 'Ledger Plumbing'): Business
{
    return Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => $name,
    ]);
}

/**
 * A location with a confirmed, scanned website, an About page and a pixel
 * sighting — the same fixture the publish slice uses, so a refusal here is this
 * slice's and not the byline gate's (398, 5752).
 */
function measurementLocation(string $host = 'ledgerplumbing.test'): Location
{
    $location = Location::factory()->create();

    $location->forceFill([
        'website_url' => 'https://'.$host,
        'website_confirmed_at' => now(),
        'website_scanned_at' => now(),
        'about_url' => 'https://'.$host.'/about',
        'about_url_confirmed_at' => now(),
    ])->save();

    measurementSighting($host);

    // ⛔ **BOTH URLS FAKED, AND `robots.txt` IS THE ONE NOTHING NAMES** (5752).
    // An unmatched URL falls through `Http::fake()`'s array and is really sent,
    // so a missing robots stub reaches DNS, `RobotsPolicy` fails closed, and the
    // publish refuses with `NoAuthorByline` — which reads exactly like a gate
    // working.
    Http::fake([
        'https://'.$host.'/robots.txt' => Http::response('', 200),
        'https://'.$host.'/about' => Http::response('<html><body>About us</body></html>', 200),
        'api.anthropic.com/*' => Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode(['flags' => []], JSON_THROW_ON_ERROR)]],
            'usage' => ['input_tokens' => 600, 'output_tokens' => 100],
        ]),
    ]);

    return $location->refresh();
}

/**
 * ⚠️ **THE SIGHTING IS WHAT MAKES THE TIER T3 RATHER THAN T4**, and without it
 * every test below would silently exercise the advisory rung instead.
 */
function measurementSighting(string $host): void
{
    DB::table('l1_events')->insert([
        'event_id' => (string) Str::uuid(),
        'business_id' => Tenancy::idOrFail(),
        'data_class' => 'pii',
        'event_type' => 'pageview',
        'consent_state' => 'granted',
        'anonymous_id' => null,
        'session_id' => null,
        'occurred_at' => now(),
        'received_at' => now(),
        'is_bot' => false,
        'bot_score' => 0,
        'page_path' => '/',
        'page_host' => $host,
        'referrer_host' => null,
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
        'device_type' => 'desktop',
        'properties' => '{}',
        'l0_path' => 'no-such-object',
        'schema_version' => 1,
    ]);
}

/**
 * An adapter that answers about a page that is not there yet, and writes it.
 *
 * ⚠️ **THE LOG DRIVER CANNOT STAND IN HERE AND ITS REFUSAL IS ASSERTED
 * ELSEWHERE** (5528, `LogCmsAdapterTest`): it answers
 * `SiteSnapshotState::Unread` and `SiteChanges::open()` refuses that, which is
 * exactly why nothing actuates on a real deployment.
 *
 * ⛔ **`snapshot()` ANSWERS `absent`, AND THAT CHANGED WHAT THE GATE BELOW
 * PROVES** (5820). It used to return `['title' => 'Nothing here yet', …]` — a
 * page with content, at the address of a page that does not exist yet — which is
 * 5753's *"a fixture asserting the thing that is in question"* exactly, and it
 * made every publish in this file an **edit**. On the merged tree a growth page
 * is a **creation**: `Publishing` routes on the snapshot's state, `apply()`
 * routes to `createPage()`, and `revert()` routes to `unpublishPage()`. The fake
 * now says the true thing, and the row-9 gate reverts a creation.
 */
function measurementAdapter(bool $rollbackSucceeds = true): CmsAdapter
{
    return new class($rollbackSucceeds) implements CmsAdapter
    {
        /** @var list<string> */
        public array $calls = [];

        public function __construct(private readonly bool $rollbackSucceeds) {}

        public function connect(Location $location): AdapterOutcome
        {
            return AdapterOutcome::ok('connected');
        }

        public function health(Location $location): AdapterHealth
        {
            return new AdapterHealth(true, 'writable');
        }

        /**
         * ⚠️ **IT ANSWERS LIKE THE ONE LIVE ADAPTER, NOT LIKE A YES-MAN**
         * (5772's rule, and `SiteChangesTest`'s fake is the reference): core
         * REST can write a title and a body and has no meta description at all.
         * A fixture that said *"everything is writable"* is the shape 5753
         * blames for hiding a defect for two slices — and here it would also
         * hide the withheld-field record from every publish in this file.
         *
         * @param  list<string>  $fields
         */
        public function fieldSupport(array $fields): FieldSupport
        {
            $writable = [];
            $refused = [];

            foreach ($fields as $field) {
                if ($field === 'meta_description') {
                    $refused[$field] = 'fake: no meta description over core REST';

                    continue;
                }

                $writable[] = $field;
            }

            return new FieldSupport($writable, $refused);
        }

        public function createPage(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'create:'.$set->url;

            return AdapterOutcome::ok('created');
        }

        /**
         * ⛔ **AN UNPUBLISH, NEVER A DELETE**, and it obeys the *other* flag on
         * this fake rather than the write one: taking a page back off is the
         * revert half, so `rollbackSucceeds` is what a test flips to model a
         * site that will not let go of the page we put on it.
         */
        public function unpublishPage(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'unpublish:'.$set->url;

            return $this->rollbackSucceeds
                ? AdapterOutcome::ok('unpublished')
                : AdapterOutcome::failed('the site did not answer');
        }

        public function writeChangeSet(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'write:'.$set->url;

            return AdapterOutcome::ok('written');
        }

        /**
         * ⛔ **ABSENT, WHICH IS AN OBSERVATION AND NOT A FAILURE** (5770). Every
         * URL this fake is asked about is a growth page that does not exist yet,
         * which is what makes the publish below a creation.
         *
         * @param  list<string>  $fields
         */
        public function snapshot(Location $location, string $url, array $fields): SiteSnapshot
        {
            $this->calls[] = 'snapshot:'.$url;

            return SiteSnapshot::absent();
        }

        public function rollback(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'rollback:'.$set->url;

            return $this->rollbackSucceeds
                ? AdapterOutcome::ok('rolled back')
                : AdapterOutcome::failed('the site did not answer');
        }

        public function uninstall(Location $location): AdapterOutcome
        {
            return AdapterOutcome::ok('uninstalled');
        }
    };
}

/**
 * One day in the pixel mart, planted directly.
 *
 * ⛔ **NO RAW EVENTS BEHIND IT, AND THAT IS `BUILD-PLAN` §2.11.4's H BULLET
 * VERBATIM**: *"the baseline is pinned to planted mart rows with no raw events
 * behind them, so the test proves the reader reads the mart"*. A reader that
 * quietly recomputed from `l1_events` would see nothing at all here.
 */
function measurementPageDay(int $businessId, string $day, string $path, int $pageviews, int $conversions = 0): void
{
    Tenancy::actingAs($businessId, function () use ($businessId, $day, $path, $pageviews, $conversions): void {
        DB::table('l2_fact_page_daily')->insert([
            'business_id' => $businessId,
            'day' => $day,
            'page_path' => $path,
            'pageviews' => $pageviews,
            'unique_views' => $pageviews,
            'exits' => 0,
            'conversions' => $conversions,
            'js_errors' => 0,
        ]);
    });
}

/**
 * Fill a closed day range in the mart with the same figure every day.
 */
function measurementPageDays(
    int $businessId,
    CarbonImmutable $from,
    CarbonImmutable $to,
    string $path,
    int $perDay,
): void {
    for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
        measurementPageDay($businessId, $day->toDateString(), $path, $perDay);
    }
}

/**
 * An applied change set, opened through the one writer there is.
 */
function measurementChange(
    Location $location,
    CarbonImmutable $appliedAt,
    string $changeType = 'growth_page',
    string $path = '/services/boiler-service',
): int {
    $changes = app(SiteChanges::class);

    $change = $changes->open(
        $location,
        new ChangeSet(
            'https://ledgerplumbing.test'.$path,
            $changeType,
            ActuationTier::T3,
            ['title' => 'Nothing here yet', 'content' => ''],
            ['title' => 'Gas boiler service', 'content' => 'A yearly check.'],
        ),
        ActuationActor::autopilot(),
    );

    $changes->apply($change, ActuationActor::autopilot());

    // The window is anchored on `applied_at`, so the fixture moves the clock
    // rather than the code.
    SiteChange::query()->whereKey($change->id)->update(['applied_at' => $appliedAt]);

    return (int) $change->id;
}

/**
 * A Search Console answer built from real response JSON.
 *
 * ⚠️ **`firstIncompleteDate` IS THE WHOLE POINT OF THE SECOND ARGUMENT.** It is
 * what tells a caller Google has not finished counting, and ignoring it is what
 * would have auto-reverted healthy pages — see the deferral test below.
 */
function measurementSearchResult(int $clicksPerDay, CarbonImmutable $from, CarbonImmutable $to, ?string $incomplete = null): SearchAnalyticsResult
{
    $rows = [];

    for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
        $rows[] = [
            'keys' => [$day->toDateString()],
            'clicks' => $clicksPerDay,
            'impressions' => $clicksPerDay * 20,
            'ctr' => 0.05,
            'position' => 8.0,
        ];
    }

    $body = ['rows' => $rows];

    if ($incomplete !== null) {
        $body['metadata'] = ['firstIncompleteDate' => $incomplete];
    }

    return SearchAnalyticsResult::fromResponse(new HttpResponse(new GuzzleResponse(
        200,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    )));
}

/**
 * Bind a Search Console client that answers with a fixed per-day click rate for
 * the baseline window and another for the measured one.
 *
 * ⚠️ **THE TWO WINDOWS ARE TOLD APART BY THE CHANGE'S OWN APPLIED DATE, NOT BY
 * THE CLOCK.** A fixture keyed on `now()` breaks the moment a test travels —
 * which the deferral test does, deliberately, by eight days.
 */
function measurementSearchClient(
    int $baselinePerDay,
    int $measuredPerDay,
    CarbonImmutable $appliedAt,
    ?string $incomplete = null,
): void {
    app()->instance(SearchConsoleClient::class, new class($baselinePerDay, $measuredPerDay, $appliedAt, $incomplete) implements SearchConsoleClient
    {
        public function __construct(
            private readonly int $baselinePerDay,
            private readonly int $measuredPerDay,
            private readonly CarbonImmutable $appliedAt,
            private readonly ?string $incomplete,
        ) {}

        public function properties(Business $business): array
        {
            return [];
        }

        public function dailyMetrics(
            Business $business,
            string $siteUrl,
            CarbonImmutable $start,
            CarbonImmutable $end,
        ): SearchAnalyticsResult {
            $isMeasured = $start->greaterThan($this->appliedAt);

            return measurementSearchResult(
                $isMeasured ? $this->measuredPerDay : $this->baselinePerDay,
                $start,
                $end,
                $isMeasured ? $this->incomplete : null,
            );
        }

        public function pageImpressions(
            Business $business,
            string $siteUrl,
            CarbonImmutable $start,
            CarbonImmutable $end,
        ): array {
            return [];
        }
    });
}

/**
 * A live, usable Google grant and a chosen property, so the search half runs.
 */
function measurementSearchGrant(Location $location): void
{
    OauthConnection::factory()->create([
        'provider' => OauthProvider::Gsc,
        'status' => ConnectionStatus::Active,
        'access_token_enc' => Crypt::encryptString('token'),
        'refresh_token_enc' => Crypt::encryptString('refresh'),
        'token_expires_at' => now()->addHour(),
    ]);

    GscSiteProperty::factory()->create(['location_id' => $location->id]);
}

function speedTenant(string $name = 'Ledger Plumbing'): Business
{
    return Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => $name,
    ]);
}

/**
 * A location whose website we can act on at T1.
 *
 * ⚠️ **THE CREDENTIAL COMES FROM THE FACTORY AND THAT IS DELIBERATE.**
 * `WordPressCredentials::connect()` is the only supported way to make one, and
 * `WordPressCredentialFactory`'s own docblock says a test wanting the *gate*
 * must go through the service. The tier is a **precondition** here, not the
 * subject: what is under test is which fixes may be applied to a T1 site, and
 * driving §19.7's least-privilege probe to get there would put the thing that
 * refuses in the wrong file.
 */
function speedLocation(string $host = 'ledgerplumbing.test'): Location
{
    $location = Location::factory()->create();

    $location->forceFill([
        'website_url' => 'https://'.$host,
        'website_confirmed_at' => now(),
        'website_scanned_at' => now(),
    ])->save();

    WordPressCredential::factory()->create([
        'location_id' => $location->id,
        'site_url' => 'https://'.$host,
        'rest_root' => 'https://'.$host.'/wp-json/',
    ]);

    return $location->refresh();
}

/**
 * An adapter that can carry a speed fix — F2's plugin, modelled.
 *
 * ⛔ **IT IS NOT A YES-MAN AND IT REFUSES A NAMED FIELD** (5778's lesson: a
 * fixture that answered "everything is writable" is half of why a real defect
 * survived two slices). It writes the seven speed fields and refuses
 * `meta_description`, exactly as core REST does, so a test asserting a refusal
 * is asserting the mechanism rather than the fixture.
 */
function speedAdapter(bool $writeSucceeds = true, bool $revertSucceeds = true): CmsAdapter
{
    return new class($writeSucceeds, $revertSucceeds) implements CmsAdapter
    {
        /** @var list<string> */
        public array $calls = [];

        public function __construct(
            private readonly bool $writeSucceeds,
            private readonly bool $revertSucceeds,
        ) {}

        public function connect(Location $location): AdapterOutcome
        {
            return AdapterOutcome::ok('connected');
        }

        public function health(Location $location): AdapterHealth
        {
            return new AdapterHealth(true, 'writable');
        }

        /**
         * @param  list<string>  $fields
         */
        public function fieldSupport(array $fields): FieldSupport
        {
            $writable = [];
            $refused = [];

            foreach ($fields as $field) {
                if ($field === 'meta_description') {
                    $refused[$field] = 'fake: no meta description over core REST';

                    continue;
                }

                $writable[] = $field;
            }

            return new FieldSupport($writable, $refused);
        }

        public function createPage(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'create:'.$set->url;

            return AdapterOutcome::ok('created');
        }

        public function unpublishPage(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'unpublish:'.$set->url;

            return AdapterOutcome::ok('unpublished');
        }

        public function writeChangeSet(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'write:'.$set->changeType;

            return $this->writeSucceeds
                ? AdapterOutcome::ok('written')
                : AdapterOutcome::failed('the site did not answer');
        }

        /**
         * ⛔ **`read`, NEVER `absent`.** A speed fix presupposes a page: an
         * absent snapshot here would have this platform *create* somebody's home
         * page in order to speed it up, and `SpeedFixes::apply()` refuses that
         * arm outright.
         *
         * @param  list<string>  $fields
         */
        public function snapshot(Location $location, string $url, array $fields): SiteSnapshot
        {
            $this->calls[] = 'snapshot:'.$url;

            $before = [];

            foreach ($fields as $field) {
                $before[$field] = 'as it was';
            }

            return SiteSnapshot::read($before);
        }

        public function rollback(Location $location, ChangeSet $set): AdapterOutcome
        {
            $this->calls[] = 'rollback:'.$set->changeType;

            return $this->revertSucceeds
                ? AdapterOutcome::ok('rolled back')
                : AdapterOutcome::failed('the site did not answer');
        }

        public function uninstall(Location $location): AdapterOutcome
        {
            return AdapterOutcome::ok('uninstalled');
        }
    };
}

/** The instant every fix in this file lands on the site. */
function speedAppliedAt(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-06-01 12:00:00');
}

/** The day the seven-day window has closed and a judgement is due. */
function speedJudgedAt(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-06-09 05:30:00');
}

/**
 * One vitals-mart bucket. `l2_fact_vital_daily` is a daily histogram, so the
 * p75 the reader reports is the bucket a cumulative count reaches — planting one
 * bucket with N samples pins it exactly.
 */
function speedVitalDay(int $businessId, string $day, WebVital $metric, DeviceClass $device, int $bucket, int $samples): void
{
    Tenancy::actingAs($businessId, function () use ($businessId, $day, $metric, $device, $bucket, $samples): void {
        DB::table('l2_fact_vital_daily')->insert([
            'business_id' => $businessId,
            'day' => $day,
            'metric' => $metric->value,
            'device_type' => $device->value,
            'bucket' => $bucket,
            'samples' => $samples,
        ]);
    });
}

/**
 * Fill a closed day range with one bucket a day.
 */
function speedVitalRange(
    int $businessId,
    CarbonImmutable $from,
    CarbonImmutable $to,
    WebVital $metric,
    int $bucket,
    int $samplesPerDay,
): void {
    for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
        speedVitalDay($businessId, $day->toDateString(), $metric, DeviceClass::Phone, $bucket, $samplesPerDay);
    }
}

function speedPageRange(
    int $businessId,
    CarbonImmutable $from,
    CarbonImmutable $to,
    int $pageviewsPerDay,
    int $errorsPerDay,
): void {
    for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
        Tenancy::actingAs($businessId, function () use ($businessId, $day, $pageviewsPerDay, $errorsPerDay): void {
            DB::table('l2_fact_page_daily')->insert([
                'business_id' => $businessId,
                'day' => $day->toDateString(),
                'page_path' => '/',
                'pageviews' => $pageviewsPerDay,
                'unique_views' => $pageviewsPerDay,
                'exits' => 0,
                'conversions' => 0,
                'js_errors' => $errorsPerDay,
            ]);
        });
    }
}

function speedTenantRange(
    int $businessId,
    CarbonImmutable $from,
    CarbonImmutable $to,
    int $sessionsPerDay,
    int $conversionsPerDay,
): void {
    for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
        Tenancy::actingAs($businessId, function () use ($businessId, $day, $sessionsPerDay, $conversionsPerDay): void {
            DB::table('l2_fact_daily_tenant')->insert([
                'business_id' => $businessId,
                'day' => $day->toDateString(),
                'sessions' => $sessionsPerDay,
                'engaged_sessions' => $sessionsPerDay,
                'bot_sessions' => 0,
                'users' => $sessionsPerDay,
                'new_users' => $sessionsPerDay,
                'pageviews' => $sessionsPerDay,
                'conversions' => $conversionsPerDay,
                'phone_clicks' => 0,
                'form_submissions' => $conversionsPerDay,
                'directions_clicks' => 0,
            ]);
        });
    }
}

/** The 14 days `28` §4.3 judges against. */
function speedBaselineWindow(): array
{
    $to = speedAppliedAt()->subDay()->startOfDay();

    return [$to->subDays(SpeedDecider::BASELINE_DAYS - 1), $to];
}

/** The 7 days `28` §4.3 judges. */
function speedMeasuredWindow(): array
{
    $from = speedAppliedAt()->addDay()->startOfDay();

    return [$from, $from->addDays(SpeedDecider::MEASURED_WINDOW_DAYS - 1)];
}
