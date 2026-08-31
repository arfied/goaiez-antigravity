<?php

declare(strict_types=1);

use App\Contracts\L0Archive;
use App\Enums\DataClassification;
use App\Models\Business;
use App\Services\Warehouse\L0Batch;
use App\Services\Warehouse\L0Receipt;
use App\Services\Warehouse\L1Loader;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| L3 fixtures — a cohort of tenants with L2 rows
|--------------------------------------------------------------------------
|
| Shared by `NetworkBenchmarksTest` and `ByteIdenticalReplayTest` rather than
| duplicated into either, on `pixel_helpers.php`'s reasoning: a global helper
| declared in one test file and used from another works only by load order, and
| a duplicated global helper name is one of the four documented causes of a run
| that prints zero bytes (694, 808).
|
| ⚠️ **THE L2 ROW IS WRITTEN DIRECTLY RATHER THAN REPLAYED, AND THAT IS A
| DELIBERATE TRADE.** `l2_fact_daily_tenant` is a pure function of `l1_events`
| and `Replayer` proves that elsewhere, at length, over a fixture built from real
| L0 objects. What is under test here is the layer **above** it, and building
| eight tenants' worth of L0 receipts to reach a session count would put the
| whole of L1 and L2's derivation between the fixture and the assertion — so a
| change in either would move these numbers and the failure would read as an L3
| defect. The one thing this cannot see is a column renamed in L2, which
| `WarehouseTest`'s snapshot-completeness lint sees instead.
|
*/

/**
 * A business with a vertical, an owner of its own, and one day of L2 facts.
 *
 * ⚠️ **AN OWNER PER BUSINESS BECAUSE THAT IS HOW THE DERIVATION FINDS THEM.**
 * `NetworkBenchmarks::businessIds()` walks users and reads each one's businesses
 * through the `owner_lookup` policy — there is no query in this schema that
 * lists every business, because `businesses` is FORCE row-level security keyed
 * on its own id. A fixture that shared one owner would still be found; one that
 * created a business with no owner would not exist at all.
 *
 * @param  array{sessions?: int, engaged_sessions?: int, conversions?: int, pageviews?: int}  $facts
 */
function benchmarkTenant(
    string $vertical,
    array $facts = [],
    string $day = '2026-08-19',
    DataClassification $classification = DataClassification::Pii,
    ?string $name = null,
): Business {
    $business = Business::factory()->create(array_filter([
        'vertical' => $vertical,
        'data_classification' => $classification,
        'name' => $name,
    ], fn (mixed $value): bool => $value !== null));

    Tenancy::actingAs((int) $business->getKey(), function () use ($business, $facts, $day): void {
        DB::table('l2_fact_daily_tenant')->insert([
            'business_id' => $business->getKey(),
            'day' => $day,
            'sessions' => $facts['sessions'] ?? 100,
            'engaged_sessions' => $facts['engaged_sessions'] ?? 50,
            'bot_sessions' => 0,
            'users' => $facts['sessions'] ?? 100,
            'new_users' => 0,
            'pageviews' => $facts['pageviews'] ?? 200,
            'conversions' => $facts['conversions'] ?? 5,
            'phone_clicks' => 0,
            'form_submissions' => 0,
            'directions_clicks' => 0,
        ]);
    });

    return $business;
}

/**
 * A cohort of `$count` tenants in one vertical, each with a distinct session
 * count so the percentiles are not all the same number.
 *
 * ⚠️ **DISTINCT VALUES ARE LOAD-BEARING** (411's shape). A cohort of eight
 * identical tenants has p25 = p50 = p75, so a percentile function that returned
 * the first element, the last, or the mean would pass every assertion about it.
 *
 * @return list<Business>
 */
function benchmarkCohort(string $vertical, int $count = 8, string $day = '2026-08-19', int $firstSessions = 10): array
{
    $businesses = [];

    for ($i = 0; $i < $count; $i++) {
        $businesses[] = benchmarkTenant(
            $vertical,
            ['sessions' => $firstSessions + $i, 'conversions' => $i, 'engaged_sessions' => $i, 'pageviews' => 10 * ($i + 1)],
            $day,
        );
    }

    return $businesses;
}

/*
|--------------------------------------------------------------------------
| L0 fixtures — the archived objects a replay is rebuilt from
|--------------------------------------------------------------------------
|
| Lived in `ByteIdenticalReplayTest` until 2026-08-20, and moved here the day a
| second file needed them: `WarehouseReplayCommandTest` drives the operator's
| entry point over the same archive the gate is built on. `Pest.php` records why
| the move rather than a `require` between two test files — a global helper
| declared in one test file and used from another works only by load order, and
| a duplicated global helper name is one of the four documented causes of a run
| that prints zero bytes (694, 808).
|
| ⚠️ **ONE FIXTURE RATHER THAN A SMALLER SECOND ONE, DELIBERATELY.** A command
| test seeded from its own hand-written envelope would derive nothing the day the
| pixel's batch shape moved, and would report that as "the command ran" — which
| is the shape the command it tests was written in. Sharing the gate's fixture
| means the command is driven over the same seven rows the byte-identical test
| counts.
|
*/

/**
 * @param  list<array<string, mixed>>  $events
 */
function l0Payload(array $events, string $source = 'google', string $medium = 'cpc', bool $webdriver = false): string
{
    // Built to match `resources/js/pixel.js`'s `flush()` exactly — the envelope
    // is per request and the events are a batch, which is why an L0 receipt is
    // one HTTP request rather than one event.
    return json_encode([
        'k' => 'pk_test',
        'schema_version' => 1,
        'sent_at' => '2026-08-17T12:00:00.000Z',
        'device' => [
            'screen_w' => 390,
            'screen_h' => 844,
            'viewport_w' => 390,
            'viewport_h' => 700,
            'language' => 'en-US',
            'timezone' => 'America/Chicago',
            'cores' => 8,
            // A float from a real browser: navigator.deviceMemory is 0.25 on a
            // small phone. It reaches `properties` only through the derivation's
            // float rule, but it is here because L0 must archive it verbatim.
            'memory' => 0.25,
            'touch' => 5,
            'network' => '4g',
            'webdriver' => $webdriver,
        ],
        'referrer_host' => 'search.example',
        'referrer_path' => '/',
        'utm' => [
            'source' => $source,
            'medium' => $medium,
            'campaign' => 'summer',
            'term' => '',
            'content' => '',
        ],
        'click_id' => ['gclid' => '', 'fbclid' => '', 'msclkid' => ''],
        'events' => $events,
    ], JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, mixed>
 */
function l0Event(string $id, string $type, string $occurredAt, string $path = '/pricing', mixed $properties = null): array
{
    return [
        'event_id' => $id,
        'type' => $type,
        'occurred_at' => $occurredAt,
        'session_id' => '11111111-1111-4111-8111-111111111111',
        'anonymous_id' => '22222222-2222-4222-8222-222222222222',
        'consent_state' => 'granted',
        'page_url' => 'https://Shop.Example.com'.$path,
        'page_path' => $path,
        'page_title' => 'Pricing',
        'properties' => $properties ?? ['is_new_session' => true, 'session_views' => 1],
    ];
}

/**
 * File one L0 object under `$host`'s prefix whose LINE claims `$claimant`.
 *
 * ⛔ **THE ONE FIXTURE THAT STILL MAKES A REPLAY THROW, SINCE `29` §2 RULE 24
 * WAS RETIRED ON 2026-08-30 (12539).** The refusal that used to serve this
 * purpose was a covered entity's; the tenancy precondition in
 * {@see L1Loader} is what survives, and it fires on an
 * L0 line whose `business_id` disagrees with the tenant being replayed.
 *
 * ⚠️ **BUILT BY `L0Batch` AND FILED UNDER THE WRONG PREFIX BY HAND**, because
 * the line has to be a real canonical L0 line or `read()` refuses it on shape
 * first, and `store()` will not write an object whose contents disagree with
 * its own path. **The line is the archive's; only the key is the test's.**
 */
function seedForeignL0(Business $host, Business $claimant, string $objectId): void
{
    Storage::disk('l0')->put(
        'class=pii/business='.$host->getKey().'/dt=2026-08-10/'.$objectId.'.jsonl.gz',
        (string) gzencode((new L0Batch(
            $objectId,
            $claimant->getKey(),
            DataClassification::Pii,
            CarbonImmutable::parse('2026-08-10T09:15:30.500Z'),
            [new L0Receipt('rf', l0Payload([l0Event(
                'cf000001-0000-4000-8000-000000000001',
                'pageview',
                '2026-08-10T09:15:29.123Z',
            )]))],
        ))->lines(), 9),
    );
}

/**
 * Three L0 objects over two UTC days, for one business.
 *
 * ⛔ **`$eventPrefix` WAS A WORKAROUND AND IS NOW A CHOICE — CORRECTED
 * 2026-08-20 (6182, 6240, 6245).** This docblock read: *"`$eventPrefix` exists
 * because `l1_events.event_id` is a global primary key, not a per-tenant one …
 * so **the second tenant to present a given event id loses the event
 * silently** … Pass a distinct prefix per business."* **The key is
 * `(business_id, event_id)` now**, so two tenants seeded with one prefix each
 * keep all seven of their rows, and passing a distinct prefix is no longer
 * required of anybody.
 *
 * ⚠️ **THE PARAMETER STAYS, AND ITS CALLERS DO OPPOSITE THINGS WITH IT ON
 * PURPOSE.** `ByteIdenticalReplayTest` keeps distinct prefixes — 6183 put them
 * there to stop a determinism test comparing a populated warehouse against an
 * empty one, and that test's claim is about the *snapshot*, so a fixture where
 * two tenants can collide would make a failure ambiguous between two subjects.
 * `L1EventKeyTest` passes **one** prefix to both tenants, deliberately, because
 * the collision is its whole subject. **Two real browsers mint independent
 * random ids; a page that emits a chosen one is what the key now survives.**
 */
function seedL0(Business $business, string $eventPrefix = 'c0000001'): void
{
    $archive = app(L0Archive::class);

    // Day one, two objects — so path ordering across objects matters.
    $archive->store(new L0Batch(
        'aaaaaaaa-0000-4000-8000-000000000001',
        $business->getKey(),
        DataClassification::Pii,
        CarbonImmutable::parse('2026-08-10T09:15:30.500Z'),
        [
            new L0Receipt('r1', l0Payload([
                l0Event($eventPrefix.'-0000-4000-8000-000000000001', 'pageview', '2026-08-10T09:15:29.123Z'),
                // ⚠️ A NON-UTC OFFSET FROM A CLIENT. The same instant as a `Z`
                // form and a different string; the derivation re-renders it, so
                // the stored bytes are a property of the event and not of the
                // browser that reported it.
                l0Event($eventPrefix.'-0000-4000-8000-000000000002', 'click', '2026-08-10T04:15:29.900-05:00', '/contact'),
            ])),
            // ⚠️ UNICODE AND AN EMPTY STRING BESIDE A NULL. `utm_term` is '' in
            // the payload and `referrer_host` is absent from this one entirely,
            // so the null-vs-absent rule is exercised on a real row.
            new L0Receipt('r2', l0Payload([
                l0Event($eventPrefix.'-0000-4000-8000-000000000003', 'pageview', '2026-08-10T09:16:00.000Z', '/café', [
                    'title' => "Prix — l'été",
                    'note' => '',
                    'missing' => null,
                ]),
            ], source: '', medium: '')),
        ],
    ));

    $archive->store(new L0Batch(
        'aaaaaaaa-0000-4000-8000-000000000002',
        $business->getKey(),
        DataClassification::Pii,
        CarbonImmutable::parse('2026-08-10T14:00:00.000Z'),
        [
            // A bot: navigator.webdriver is 30 points and 64 cores is 15, which
            // clears §12's threshold of 60 only with both — so this one is NOT a
            // bot and the next batch's is, which keeps the bot column from being
            // constant across the fixture.
            new L0Receipt('r3', l0Payload([
                l0Event($eventPrefix.'-0000-4000-8000-000000000004', 'form_submitted', '2026-08-10T14:00:01.001Z'),
            ], webdriver: true)),
            // ⚠️ A DUPLICATE event_id, ACROSS OBJECTS. §11.5 dedupes on it and a
            // retried beacon is exactly how this arrives. The rebuild must land
            // one row, deterministically, on both runs.
            new L0Receipt('r4', l0Payload([
                l0Event($eventPrefix.'-0000-4000-8000-000000000001', 'pageview', '2026-08-10T09:15:29.123Z'),
            ])),
            // ⛔ A MALFORMED PAYLOAD. It must be archivable and rejected
            // deterministically — throwing would make every future replay of
            // this range fail forever, and L0 cannot be edited.
            new L0Receipt('r5', '{"events": [ truncated'),
        ],
    ));

    // A second day, so the L2 date dimension has more than one value and the
    // day-boundary truncation is doing something.
    $archive->store(new L0Batch(
        'aaaaaaaa-0000-4000-8000-000000000003',
        $business->getKey(),
        DataClassification::Pii,
        CarbonImmutable::parse('2026-08-11T23:59:59.999Z'),
        [
            new L0Receipt('r6', l0Payload([
                l0Event($eventPrefix.'-0000-4000-8000-000000000005', 'pageview', '2026-08-11T23:59:59.000Z'),
                l0Event($eventPrefix.'-0000-4000-8000-000000000006', 'vital', '2026-08-11T23:59:59.500Z', '/', [
                    // ⛔ THE FLOAT THE PIXEL ACTUALLY SENDS: `record('vital', {
                    // metric: 'CLS', value: Math.round(cls * 1000) / 1000 })`.
                    'metric' => 'CLS',
                    'value' => 0.001,
                ]),
                // ⛔ **AND THE INTEGER ONE BESIDE IT, WHICH IS A DIFFERENT
                // EXTRACTION ARM AND NOT A SECOND EXAMPLE OF THE SAME ONE**
                // (decision 5604). `canonicalise()` renders the float above as a
                // `%.17G` **string** and leaves this one a JSON **number**, so
                // the vitals mart reads them through two different branches. A
                // fixture carrying only the float would leave the number arm
                // untested by the one gate that compares bytes; carrying only
                // the integer would leave CLS — the metric the whole float rule
                // exists for — outside it.
                l0Event($eventPrefix.'-0000-4000-8000-000000000008', 'vital', '2026-08-11T23:59:59.600Z', '/', [
                    'metric' => 'LCP',
                    'value' => 2500,
                ]),
            ], source: 'newsletter', medium: 'email')),
        ],
    ));
}

/**
 * One event, archived twice — once before UTC midnight and once after it.
 *
 * ⚠️ **THIS IS `navigator.sendBeacon` BEHAVING NORMALLY, NOT A HOSTILE CASE.**
 * `resources/js/pixel.js` re-queues a batch the browser could not deliver, so
 * two receipts of one `event_id` is the shape §11 row 5's idempotency exists
 * for. What makes it reach this file is only *when* the second one lands.
 *
 * ⚠️ **HERE RATHER THAN IN THE TEST THAT DRIVES IT, ON THIS FILE'S OWN
 * PRECEDENT** (694, 808): a global helper declared in one test file and used
 * from another works only by load order, and a duplicated global helper name is
 * one of the documented causes of a run that prints zero bytes. It is shared by
 * `RangeIndependenceTest` and `WarehouseReplayCommandTest`, exactly as
 * `seedL0()` came to be shared.
 *
 * @param  string  $eventId  distinct per business, so two tenants in one test
 *                           are two independent derivations
 */
function seedRetryAcrossMidnight(
    Business $business,
    string $eventId,
    DataClassification $second = DataClassification::Pii,
): void {
    $archive = app(L0Archive::class);

    $event = l0Event($eventId, 'pageview', '2026-08-10T23:59:58.000Z', '/late');

    $archive->store(new L0Batch(
        'bbbbbbbb-0000-4000-8000-000000000001',
        $business->getKey(),
        DataClassification::Pii,
        CarbonImmutable::parse('2026-08-10T23:59:59.000Z'),
        [new L0Receipt('r1', l0Payload([$event]))],
    ));

    $archive->store(new L0Batch(
        'bbbbbbbb-0000-4000-8000-000000000002',
        $business->getKey(),
        $second,
        CarbonImmutable::parse('2026-08-11T00:00:02.000Z'),
        [new L0Receipt('r2', l0Payload([$event]))],
    ));
}
