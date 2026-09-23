<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Business;
use App\Models\User;
use App\Services\Sms\TenantNumbers;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use RuntimeException;
use Tests\Concerns\RefreshesTenantDatabase;

abstract class TestCase extends BaseTestCase
{
    // // use RefreshesTenantDatabase;

    /**
     * ⚠️ LIVEWIRE'S ASSET-INJECTION FLAG IS A CLASS STATIC AND SURVIVES THE
     * APPLICATION REFRESH BETWEEN TESTS.
     *
     * `SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest` is set when
     * a component renders, and on the next HTML 200 response Livewire injects
     * its scripts and styles into the markup. In a real request the `flush-state`
     * hook clears it on termination; a test's `$this->get()` never terminates,
     * so **a test file that ends on a rendered Livewire page leaves the flag set
     * for whatever file runs next.**
     *
     * The victim is `MarketingPagesTest`'s *"the home page ships no component
     * runtime"* — decision 259's claim, and one with an LCP budget behind it. It
     * fails or passes depending on **what ran before it**, which makes a real
     * assertion into a coin toss: it was green only because no test file had yet
     * happened to end on a successful admin page render. `LegalDocumentIndexTest`
     * is the first that does.
     *
     * Reset here rather than in that one file, because the next such file gets
     * the same protection without anybody knowing this exists. Production is
     * unaffected either way — the marketing home renders no component, so the
     * flag is false on a real request, which is exactly what the assertion
     * says.
     */
    protected function setUp(): void
    {
        $this->pinTheClockIfDriven();

        parent::setUp();

        SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;
        SupportAutoInjectedAssets::$forceAssetInjection = false;
    }

    protected function tearDown(): void
    {
        try {
            $this->releaseNumbers();
        } finally {
            parent::tearDown();
        }
    }

    protected function releaseNumbers(): void
    {
        try {
            // Release numbers so journeys committing their transactions do not exhaust the pool
            DB::table('phone_numbers')
                ->where('e164', 'like', '+1512556%')
                ->update([
                    'business_id' => null,
                    'location_id' => null,
                    'role' => 'shared_pool',
                    'state' => 'provisioning',
                    'state_reason' => 'Released in teardown',
                ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '25P02') {
                throw $e;
            }
        }
    }

    /**
     * ⛔ **`TEST_CLOCK` PINS THE WHOLE SUITE'S `now()`, AND IT EXISTS BECAUSE A
     * TEST THAT TRAVELS ACROSS A PERIOD BOUNDARY IS RED ON TWO DAYS IN THIRTY
     * AND NOBODY FINDS OUT FOR A YEAR** (12451, 12520–12539).
     *
     * `MonthlyEventCapTest` travelled two days while `MonthlyEventCap` keys its
     * usage row on the **UTC** month, so that file was red on the last two days
     * of every month — and it fired in a pre-push hook, on a branch that had
     * nothing to do with it. ⚠️ **The suite that passed an hour earlier and the
     * hook that failed were on opposite sides of a UTC midnight**, which is
     * exactly why it read as a regression the branch had caused.
     *
     * ⛔ **A LINT CANNOT FIND THE REST OF THAT POPULATION, AND THIS IS THE
     * REASON A CENSUS NEEDED AN INSTRUMENT RATHER THAN A GREP.** The subject is
     * *a test that travels across a boundary the code under test keys on* — a
     * fact about two files at once and about the arithmetic between them. No
     * token names it: the defect in `MonthlyEventCapTest` was spelled
     * `travel(2)->days()` and the boundary it crossed was spelled
     * `$at->utc()->startOfMonth()`, in a different file, with nothing lexical
     * in common. **So the instrument is a clock you can drive**, and the census
     * it produced is `docs/DECISIONS.md` 12520–12539.
     *
     * Unset — which is CI, the pre-push hook and every ordinary run — this does
     * nothing whatever. Set to an instant, it pins `Carbon::setTestNow()` before
     * the application boots for each test, so `travel()`, `travelTo()` and
     * `freezeTime()` all compose on top of it:
     *
     *     TEST_CLOCK='2026-08-31T23:30:00Z' php artisan test --compact --testsuite=Unit,Feature
     *
     * ⚠️ **IT IS A FLOOR AND NOT A CEILING.** `travelBack()` and a bare
     * `Carbon::setTestNow()` return that test to the REAL clock, and this suite
     * does one or the other in 88 places. A body that unpins itself is measured
     * on whatever day the run happens to fall — which is the state this
     * instrument exists to make visible, not a hole in it.
     *
     * ⛔ **IT REFUSES THE BROWSER SUITE RATHER THAN HALF-PINNING IT.** A browser
     * test drives a real HTTP server in a separate process that never sees this
     * variable, so the client would be pinned and the server would not, and
     * every assertion spanning the two would compare instants from different
     * calendars. ⚠️ **No `tests/Browser/*.php` file manipulates the clock at
     * all** — measured 2026-08-30, `travel(`, `travelTo(`, `setTestNow` and
     * `freezeTime` are absent from that whole directory — **so the refusal
     * costs the census nothing**, and it is a refusal rather than a silent skip
     * because a run that quietly ignored the pin would report a clean arm it
     * had never driven.
     */
    private function pinTheClockIfDriven(): void
    {
        $clock = getenv('TEST_CLOCK');

        if (! is_string($clock) || trim($clock) === '') {
            return;
        }

        if (self::clockPinIsUnsafeFor(static::class)) {
            throw new RuntimeException(
                'TEST_CLOCK cannot drive the Browser suite: the HTTP server it '
                .'talks to runs in a separate process on the real clock, so the '
                .'pin would apply to one side of every assertion. Unset it, or '
                .'select --testsuite=Unit,Feature.'
            );
        }

        Carbon::setTestNow(CarbonImmutable::parse(trim($clock), 'UTC'));
    }

    /**
     * Whether pinning the clock for `$testClass` would pin only one side of it.
     *
     * ⚠️ **PUBLIC BECAUSE IT IS DRIVEN, NOT BECAUSE ANYTHING ELSE CALLS IT.**
     * The guard above turns on a class NAME, which is a claim about Pest's
     * code rather than about ours, and a guard that quietly stopped matching
     * would half-pin the Browser suite in silence. `TimeTest` drives this with
     * the names Pest generates for the files actually on disk — in both
     * directions, because the guard an author writes first matches the bare
     * word `Browser` and would refuse `BrowserCoverageTest` and
     * `BrowserInstrumentTest`, which are Feature files.
     */
    public static function clockPinIsUnsafeFor(string $testClass): bool
    {
        return str_contains(str_replace('\\', '/', $testClass), 'Tests/Browser');
    }

    public static function provisionTenant(array $attributes = []): Business
    {
        // The pool uses the 556 exchange to avoid colliding with hand-written fixtures on 555 (693c).
        static $numberSeed = 1000;
        app(TenantNumbers::class)->addToPool('+1512556'.$numberSeed++);

        $owner = isset($attributes['owner_user_id']) ? User::find($attributes['owner_user_id']) : User::factory()->create();
        $name = $attributes['name'] ?? 'Test Business';
        $biz = app(TenantProvisioner::class)->provision($owner);

        $updates = ['name' => $name];
        if (isset($attributes['currency'])) {
            $updates['currency'] = $attributes['currency'];
        }
        if (isset($attributes['owner_user_id'])) {
            $updates['owner_user_id'] = $attributes['owner_user_id'];
        }
        if (! empty($updates)) {
            $biz->update($updates);
        }

        Tenancy::set($biz->id);

        return $biz;
    }
}
