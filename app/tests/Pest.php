<?php

use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ComplianceList;
use App\Enums\ConsentType;
use App\Enums\LegalDocumentType;
use App\Enums\MailFeedbackSignal;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Models\Customer;
use App\Models\LegalDocument;
use App\Models\PlatformSetting;
use App\Notifications\MagicLinkLogin;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentCapture;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Consent\SuppressionRegistry;
use App\Services\Legal\SignupTerms;
use App\Services\Mail\MailQuota;
use App\Support\DefaultsManifest;
use App\Support\SqlState;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

// The architecture lints were one ~4,900-line file until they were split by
// domain; their shared helpers moved here rather than being duplicated into each
// domain file, because two copies of one rule are two rules that can drift.
// An explicit require because `tests/` is PSR-4 for classes and these are plain
// functions — nothing autoloads them.
require_once __DIR__.'/Support/architecture_helpers.php';

// Tenant shapes the provisioner cannot build on its own — chiefly the
// multi-location tenant, whose absence from the harness is why four owner
// screens degraded unnoticed (3061). Same reasoning as the line above.
require_once __DIR__.'/Support/tenant_helpers.php';

// The pixel collector's fixtures, shared by two Feature files. Here rather than
// in one of them because a global helper declared in a test file and used from
// another works only by load order — and a duplicated global helper name is one
// of the four documented causes of a run that prints zero bytes (694, 808).
require_once __DIR__.'/Support/pixel_helpers.php';

// A cohort of tenants with L2 facts, for the L3 network layer — shared by the
// derivation's own tests and by the byte-identical gate, for the same reason.
require_once __DIR__.'/Support/warehouse_helpers.php';

// The wide browser console collector. Here rather than inside a Browser file
// because that is exactly what went wrong: wave 33 built a collector that saw
// what the plugin cannot, put it inside `AccountScreenTest.php`, and every
// other Browser file went on calling the blind assertion (10201).
require_once __DIR__.'/Support/browser_console_helpers.php';

// The actuation chain's fixtures — a tenant with a website, the `CmsAdapter`
// fakes, and the mart planting the measurement and speed slices both need.
// `speedAdapter()` already lived in one Feature file and was called from
// another, so this file is that hazard closed rather than avoided (5960).
require_once __DIR__.'/Support/actuation_helpers.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

// RefreshesTenantDatabase, not Laravel's RefreshDatabase: the default connection
// is a non-owner role that cannot create tables, so stock RefreshDatabase fails on
// the first CREATE TABLE. The trait migrates as the owner and transacts as the
// runtime role, which is what keeps RLS applying to tests. See its docblock.
pest()->extend(TestCase::class)
    ->use(RefreshesTenantDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshesTenantDatabase::class)
    ->in('Modules');

pest()->extend(TestCase::class)
    ->use(RefreshesTenantDatabase::class)
    ->in('Journeys');

// Browser tests get the same database lifecycle. They need it for the same
// reason and one more: a real browser hits a real HTTP server in a separate
// process, so anything the test seeds has to be committed rather than left in
// an open transaction the server's connection cannot see.
pest()->extend(TestCase::class)
    ->use(RefreshesTenantDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Canonicalises a jsonb round-trip so it can be compared with toBe().
 *
 * Postgres jsonb normalises the key order of a JSON *object* on storage — a
 * stored `{"destination": ..., "threshold": ...}` reads back with its keys
 * reordered by jsonb's own internal rules, never the order it was written in.
 * JSON *array* (list) element order survives jsonb storage untouched, so only
 * object keys need canonicalising, never list order.
 *
 * toEqual() "fixes" the ordering problem too, but only by falling back to
 * loose (`==`) comparison recursively, which also stops catching a type
 * regression on every value in the structure — `threshold` round-tripping as
 * the string `"5"` instead of the integer `5`, or a boolean as `"1"` instead of
 * `true`, would still pass. Sorting both sides' object keys
 * recursively and comparing the result with toBe() (`===`) keeps type
 * strictness while still tolerating the key reordering jsonb itself performs.
 *
 * @param  array<array-key, mixed>  $value
 * @return array<array-key, mixed>
 */
function sortJsonKeysRecursively(array $value): array
{
    if (! array_is_list($value)) {
        ksort($value);
    }

    return array_map(
        fn (mixed $item): mixed => is_array($item) ? sortJsonKeysRecursively($item) : $item,
        $value,
    );
}

/**
 * Put the mail configuration in a state `PlatformMailer` will actually send from.
 *
 * ⚠️ **THE SUITE'S DEFAULT MAILER IS `array`, AND `PlatformMailer` CORRECTLY
 * REFUSES IT** — that is decision 700's whole point, and the guard is left live
 * in tests rather than switched off in the testing environment. A guard that is
 * inert across 1,400 tests is a guard nobody is exercising.
 *
 * So a test that wants to observe a send has to say so, in one line, and the
 * failure mode for forgetting is loud: the notification simply never reaches
 * `Notification::fake()` and the assertion fails with `MailNotDeliverable` in
 * the log beside it.
 *
 * The transport is never contacted — every caller of this pairs it with
 * `Notification::fake()` or `Mail::fake()`, which intercept above it. `smtp`
 * here means "a name that is not on the undeliverable list", nothing more.
 */
function mailerCanDeliver(): void
{
    // ⛔ **THE CEILING IS PART OF "CAN DELIVER" SINCE 4603, AND THE HELPER HAS
    // TO MODEL THE DEPLOYMENT RATHER THAN THE LINE** — 4436's rule, which had
    // to be applied to `customerMailIsPermitted()` for the same reason. The
    // `smtp` mailer carries **no seeded ceiling** (4604), so a deployment that
    // sets nothing but `MAIL_MAILER=smtp` sends nothing at all; a helper that
    // switched the transport and stopped there would be modelling exactly the
    // misconfiguration the refusal exists to catch, and every test using it
    // would fail for a reason that has nothing to do with its subject.
    //
    mailSendingCeilingIsSet();

    config([
        'mail.default' => 'smtp',
        // Not the framework placeholder, not the primary host, and **on the
        // sending domain exactly** — the three things assertDeliverable()
        // checks after the transport. `app.url` is `http://localhost` under
        // phpunit.xml.dist, so the primary-domain rule is satisfied by any
        // address that is not on `localhost`.
        'mail.from.address' => 'noreply@'.platformSendingDomain(),
    ]);
}

/**
 * State a 24-hour sending ceiling for a mailer, because a deliverable
 * deployment has one.
 *
 * ⛔ **THE `smtp` MAILER CARRIES NO SEEDED CEILING** (4603, 4604): every figure
 * this codebase could state for an arbitrary relay is a false one, so an
 * operator states it and until they do that transport sends **nothing**. A
 * fixture that switched the transport and stopped there would model exactly the
 * misconfiguration the refusal exists to catch, and every test using it would
 * fail for a reason unrelated to its subject — 4436's rule, which
 * `customerMailIsPermitted()` had to learn the same way.
 *
 * ⚠️ **IT IS A NAMED HELPER BECAUSE FIVE PLACES NEEDED IT AND FOUR OF THEM HAD
 * ALREADY COPIED `mailerCanDeliver()` BY HAND.** `RenewalReminderTest`,
 * `GrandfatheredPricingTest`, `Billing\FounderOfferTest` and
 * `Agent\ThreadCloseSummaryTest` each set `mail.default` and
 * `mail.from.address` in their own `beforeEach` rather than calling the shared
 * helper, so the full suite found nine failures in files with nothing to do
 * with mail. **Copies of a fixture are where a deployment fact goes stale**, and
 * this is the smallest thing that could be shared without churning four
 * unrelated files' from-addresses.
 *
 * ⚠️ **The figure is arbitrary and deliberately not SES's.** What matters here
 * is that an operator has stated one; a test whose subject *is* the ceiling
 * overwrites this row with its own number.
 */
function mailSendingCeilingIsSet(string $mailer = 'smtp', int $ceiling = 2000): void
{
    PlatformSetting::write(MailQuota::ceilingKeyFor($mailer), $ceiling, 'test');
}

/**
 * A real email permit, minted the only way one can be minted.
 *
 * ⚠️ **NOT A HAND-BUILT `SendPermit`, WHICH IS IMPOSSIBLE ANYWAY** — its
 * constructor is private and `ConsentService` is the only thing that can grant
 * one (285). Going through the real gate also means a test exercises the same
 * object the send path receives, rather than a stand-in that happens to have the
 * right fields.
 *
 * ⚠️ **IT LIVES HERE RATHER THAN IN ONE TEST FILE BECAUSE TWO FILES NEEDED IT
 * AND THE SECOND ONE WOULD HAVE COPIED IT.** It was written inside
 * `MailTrackingCodeTest`, which meant `PlatformMailerTest` could only reach it
 * when both files happened to be loaded — every `--filter` run against one file
 * alone would have died on an undefined function, which 694 and 808 record as a
 * run that prints zero bytes and reads as an environment problem. There is
 * already a near-identical `emailPermitFor()` in `PlatformTexterTest`; that
 * duplication is left alone rather than merged, because collapsing two global
 * helpers into one is exactly the fatal those decisions describe and it belongs
 * to a lane that is not this one.
 */
function permitFor(Customer $customer): SendPermit
{
    $consent = app(ConsentService::class);

    $consent->record(
        customer: $customer,
        channel: OutreachChannel::Email,
        capture: new ConsentCapture(
            capturedBy: CapturedBy::Platform,
            captureSurface: CaptureSurface::FeedbackPage,
            consentType: ConsentType::ExpressWritten,
            disclosureVersion: '2026-07-01',
            method: 'checkbox',
            proof: [
                'ip_hash' => hash('sha256', 'mail-tracking-code-test'),
                'ts' => now()->toIso8601String(),
                'url' => 'https://example.test/f/slug',
                'user_agent' => 'Mozilla/5.0 (test)',
                'checkbox_state' => 'checked_by_user',
                'wording' => '2026-07-01',
            ],
        ),
        actor: 'test',
    );

    return $consent->permit($customer, OutreachChannel::Email, OutreachPurpose::Transactional)
        ?? throw new RuntimeException('The consent gate refused a permit these tests depend on.');
}

/**
 * Additionally put the mail configuration in a state customer-facing mail is allowed from.
 *
 * ⛔ **THIS IS OPEN QUESTION H AND IT IS A SEPARATE OPT-IN ON PURPOSE.**
 * `PlatformMailer::sendToCustomer()` refuses every transport whose feedback
 * signal is not `Typed` — a transport that cannot report a bounce or a complaint
 * cannot be used to email somebody else's customer, because a bad address can
 * never be suppressed and a spam report can never be seen (2069, 2094). That
 * refusal is live in the suite rather than switched off, the same way
 * `mailerCanDeliver()` leaves decision 700's guard live.
 *
 * ⚠️ **CALLING THIS DOES NOT WEAKEN THE REFUSAL, AND THAT IS WORTH SAYING
 * BECAUSE IT WOULD BE THE EASY MISTAKE.** The refusal is driven directly, on the
 * transports that actually ship, by `PlatformMailerTest` — `smtp` with no
 * feedback configured, and `gmail`, which is `ndr_only`. ⚠️ **That second one
 * was the *primary* transport at soft launch under 2093 and R16 has reversed
 * it**: SES is primary and Workspace stays behind the seam for staff, support
 * and inbound mail. The refusal is unchanged by the reversal, because it was
 * never about primacy — Google runs no per-recipient complaint feedback loop,
 * so an address that reports us as spam can never be suppressed on it. What
 * this helper does is let a test whose subject is something else (an invite's
 * links, its idempotency key) reach the send at all, by declaring the transport
 * SES is.
 *
 * ⚠️ **IT DOES NOT TOUCH THE TRANSPORT** — see `customerMailCanDeliver()` below
 * for why the two are deliberately not one call.
 */
function customerMailIsPermitted(): void
{
    config([
        'platform_mail.feedback.'.config('mail.default') => MailFeedbackSignal::Typed->value,

        // ⛔ **AND THE TOPIC, BECAUSE SINCE R16 THE LINE ABOVE NO LONGER MEANS
        // ANYTHING ON ITS OWN.** `MailDrivers` degrades a `typed` declaration
        // to `None` while `sns.topic_arns` is empty — that combination would
        // otherwise permit mail to somebody else's customer over a bounce feed
        // whose every genuine event is refused with a 401. This helper's job is
        // to model *the deployment in which customer mail is permitted*, and
        // that deployment has a subscribed topic; declaring only the signal
        // would model the misconfiguration the guard exists to catch.
        //
        // ⚠️ A LITERAL RATHER THAN `SNS_TOPIC`. That constant is declared at
        // file-load time in `SnsMessageVerifierTest`, and this helper is
        // reachable from tests that never load it. The value is arbitrary here:
        // nothing verifies a signature on the sending path, and what is under
        // test is only that the allowlist is non-empty.
        'platform_mail.sns.topic_arns' => ['arn:aws:sns:us-east-1:123456789012:goaiez-mail'],
    ]);

    // ⚠️ **AND THE CAN-SPAM FOOTER'S ADDRESS, BECAUSE A COMMERCIAL SEND IS
    // REFUSED WITHOUT ONE** (T176 P21). `mail.postal_address` ships with no seed
    // on purpose (decision 4019) and `ReviewInviteEmail` is commercial, so every
    // test that reaches the customer-mail path needs it — which is exactly the
    // set of tests that call this helper. Putting it here rather than in twenty
    // files is `platformSendingDomain()`'s lesson: decision 2114 took roughly
    // twenty tests across five unrelated lanes down at once, because a
    // send-path precondition was expressed as a literal in each of them.
    //
    // ⛔ **THE REFUSAL IS STILL LIVE AND IS DRIVEN DIRECTLY**, by
    // `CanSpamMailTest` and `PlatformMailerTest`, which set the transport up by
    // hand and leave this row absent. Calling this helper does not weaken it,
    // for the same reason the paragraph above says about open question H.
    platformPostalAddressIsSet();
}

/**
 * The platform's own mailing address, as a test fixture.
 *
 * ⚠️ **DELIBERATELY NOT A REAL ADDRESS AND DELIBERATELY NOT ANYBODY'S.**
 * `CLAUDE.md` forbids real personal data in a fixture, and `example.test` has an
 * accepted street-address equivalent in RFC 5737's documentation ranges only for
 * IPs — so this uses an obviously-fictional one that still has the shape a
 * renderer has to handle: multiple lines, a suite, a ZIP.
 */
function platformPostalAddress(): string
{
    return "GO AI EZ\n100 Example Way, Suite 200\nSpringfield, IL 62704";
}

/**
 * Put the CAN-SPAM postal address in place.
 *
 * Through `DefaultsRegistry::set()` rather than a direct row write, so a test
 * exercises the path an operator would actually take — and so the registry
 * chokepoint lint has nothing to object to.
 */
function platformPostalAddressIsSet(): void
{
    app(DefaultsRegistry::class)->set('mail.postal_address', platformPostalAddress(), 'test');
}

/**
 * Both halves: a transport that delivers, and permission to mail a customer over it.
 *
 * ⚠️ **THE TWO ARE SEPARATE BECAUSE `mailerCanDeliver()` SWITCHES THE TRANSPORT
 * TO `smtp`, AND A TEST WITHOUT A MAIL FAKE THEN OPENS A SOCKET.** Folding them
 * together made three tests in `InviteResumeTest` try to reach
 * `127.0.0.1:2525` and fail with a connection refused — a test suite reaching
 * for the network, which is the thing that must not be possible. A test whose
 * subject is the *bookkeeping* around an invite (was a row written, was it
 * marked deferred) wants `customerMailIsPermitted()` alone: the `array`
 * transport stays, the queued job fails at `assertDeliverable()` exactly as it
 * does in production with no relay configured, and the `outreach_messages` row
 * — which is what those tests count — is still written.
 */
function customerMailCanDeliver(): void
{
    mailerCanDeliver();
    customerMailIsPermitted();
}

/**
 * The one domain this platform sends from, read from the seed rather than typed.
 *
 * ⚠️ **THIS FIXTURE USED TO SAY `noreply@reports.goaiez.test` AND DECISION 2114
 * IS WHY IT NO LONGER CAN.** `PlatformMailer::assertSendingDomain()` requires the
 * from address to be on `mail.sending_domain` *exactly*, so the old fixture stopped
 * being deliverable the moment that guard landed — and it took roughly twenty tests
 * across five unrelated lanes down with it (magic link, the export mail, the
 * impersonation summary, the review invite, the first-week path), none of which
 * mention mail in their names. That breadth is the argument for deriving it here:
 * a literal in this function is a literal that has to be found again on the next
 * reversal, and there have been six.
 *
 * ✅ **THE NEXT REVERSAL ARRIVED ON 2026-08-19 AND THIS FUNCTION COST NOTHING**
 * (5500, moving the domain off `goaiez.com` entirely). What it did not cover was
 * the roughly ten test files that spelled the old domain out anyway, in a
 * `mail.from.address`, a `sending_account` fixture or a plus-addressed reply —
 * every one of which had to be found by grep. **A literal is what went stale
 * this time too**, so those now call this function as well.
 *
 * ⚠️ **READ FROM THE MANIFEST, NOT FROM A LITERAL, BECAUSE THE MANIFEST IS WHAT
 * SEEDS THE ROW THE GUARD READS.** A second copy of the domain in the test suite
 * would let the seed move while every test kept passing against the old value —
 * the drift `ArchitectureTest`'s manifest parse exists to prevent one table over.
 *
 * ⚠️ **THE MANIFEST RATHER THAN `DefaultsRegistry`, AND THAT IS NOT A STYLE
 * CHOICE.** Pest evaluates a `->with([...])` dataset array while *collecting*
 * tests, before the application has booted and before any database exists, so a
 * container call here fails at collection with `DatasetMissing` — a message that
 * names the test's arguments and says nothing about mail. `settings()` is a pure
 * static array, so it answers at collection and at run time alike.
 */
function platformSendingDomain(): string
{
    $seed = DefaultsManifest::settings()['mail.sending_domain']['seed'] ?? null;

    // Not a fallback with a default. If the seed is missing the tests that
    // depend on a deliverable mailer are meaningless, and a quietly substituted
    // domain would make them pass while proving nothing.
    if (! is_string($seed) || trim($seed) === '') {
        throw new RuntimeException(
            'The mail.sending_domain seed is missing, so no test can construct a deliverable '
            .'from address. Decision 5500 set it to goaieasy.net in DefaultsManifest.'
        );
    }

    return trim($seed);
}

/**
 * Run a callback with the `pgsql` connection replaced by one that refuses every
 * statement, and return the SQLSTATE that escaped.
 *
 * ⚠️ **THE REAL CONNECTION IS NEVER PURGED, AND THAT IS THE WHOLE DESIGN.**
 * `RefreshesTenantDatabase` holds the current test's transaction open on it, so
 * `DB::purge('pgsql')` — the obvious way to make `DB::extend()` take effect —
 * would disconnect and roll that transaction back underneath the suite. The
 * container binding the facade resolves is swapped instead, for the length of
 * one call, and restored in a `finally` whether the callback throws or not.
 *
 * This exists for the guards that catch `QueryException` and rethrow everything
 * except one SQLSTATE — `Tenancy::applyToDatabase()` and
 * `ReadOnlyConnection::set()` today. The *swallowed* direction can be driven with
 * a genuinely aborted transaction; the *rethrown* direction cannot, because every
 * other failure needs a connection that fails on demand.
 *
 * Fails closed: if the swap ever stops taking effect the real connection answers,
 * the statement succeeds, and this returns `'no exception was thrown'` — which is
 * never a SQLSTATE, so no caller's assertion can pass by accident.
 *
 * @param  string  $refuseWith  the SQLSTATE the refusing connection reports
 * @param  callable(): mixed  $callback
 */
function refusingConnectionSqlState(string $refuseWith, callable $callback): string
{
    $real = app('db');

    $refusing = new class($refuseWith)
    {
        public function __construct(private string $sqlState) {}

        /**
         * @param  array<array-key, mixed>  $bindings
         */
        public function statement(string $query, array $bindings = []): bool
        {
            $previous = new PDOException('refused for the length of one call');
            $previous->errorInfo = [$this->sqlState, 7, 'ERROR: refused for the length of one call'];

            throw new QueryException('pgsql', $query, $bindings, $previous);
        }
    };

    $manager = new class($refusing)
    {
        public function __construct(private object $connection) {}

        public function connection(?string $name = null): object
        {
            return $this->connection;
        }
    };

    app()->instance('db', $manager);
    DB::clearResolvedInstance('db');

    try {
        $callback();

        return 'no exception was thrown';
    } catch (QueryException $e) {
        return SqlState::of($e) ?? 'null';
    } finally {
        app()->instance('db', $real);
        DB::clearResolvedInstance('db');
    }
}

/**
 * Load every scrubbing register a marketing send depends on.
 *
 * ⚠️ **`isLoaded()` TAKES ALL THREE SINCE 1600, AND IT USED TO TAKE ANY ONE.**
 * Before that a test wanting to reach a *later* gate loaded whichever register
 * was cheapest to write — usually the litigator list — and every one of those
 * lines was quietly asserting that one register is enough, which is exactly the
 * production behaviour 1600 removed. This helper is the honest replacement: a
 * test that says "assume the tenant has scrubbed" says it in one call, and the
 * day a fourth register joins `REQUIRED_FOR_MARKETING` there is one place to
 * add it rather than a dozen.
 *
 * ⚠️ **AND THE CHANNEL IS PART OF THE ANSWER SINCE 1611.** `isLoaded()` filters
 * on `identifier_type`, so loading a register for `email` no longer opens the
 * SMS gate — which was the live exposure, not the theoretical one its docblock
 * described. The parameter defaults to `Sms` because that is the channel almost
 * every caller here means; a test about the split passes both.
 *
 * The identifiers are placeholders that match nobody. A test that wants a
 * *specific* identifier refused loads that identifier itself — this only gets
 * past `RegistryNotLoaded`.
 *
 * ⚠️ **AND THEY HAVE TO SUIT THE CHANNEL, WHICH IS A TRAP THIS HELPER HIT ON THE
 * DAY IT LEARNED ABOUT CHANNELS.** `SuppressionRegistry::load()` skips anything
 * `Identifier::hash()` cannot normalise rather than throwing — correct for a
 * million-row federal extract, silent here — so a phone number loaded against
 * `email` writes **no rows at all** and the helper reports success while leaving
 * the gate closed. That is exactly the shape the registers exist to catch,
 * inside the fixture that stands in for them.
 *
 * Defined here rather than in a domain file because four feature files need it,
 * and a second copy under another name is how two spellings of one rule drift
 * (694 and 808's zero-byte run was a *duplicated* global name, so this one is
 * declared exactly once).
 */
function loadEveryRequiredRegister(OutreachChannel $channel = OutreachChannel::Sms): void
{
    $registry = app(SuppressionRegistry::class);

    $identifiers = $channel === OutreachChannel::Email
        ? ['nobody-0001@example.test', 'nobody-0002@example.test', 'nobody-0003@example.test']
        : ['+15559990001', '+15559990002', '+15559990003'];

    $registry->load(ComplianceList::FederalDnc, $channel, [$identifiers[0]], 'test fixture');
    $registry->load(ComplianceList::Litigator, $channel, [$identifiers[1]], 'test fixture');
    $registry->load(
        ComplianceList::ReassignedNumber,
        $channel,
        [$identifiers[2]],
        'test fixture',
        null,
        CarbonImmutable::parse('2000-01-01'),
    );

    // ⚠️ THE FIXTURE ASSERTS ITS OWN POSTCONDITION. A helper that silently
    // loaded nothing would make every test that depends on it pass for the
    // wrong reason — the gate would be refusing on `RegistryNotLoaded` while
    // the test believed it had reached a later one (398).
    if (! $registry->isLoaded($channel)) {
        throw new RuntimeException(
            'loadEveryRequiredRegister() wrote no usable rows for ['.$channel->value.']. '
            .'The placeholder identifiers do not normalise on that channel.',
        );
    }
}

/**
 * Publish a version of every document a business accepts at signup (T176 P22).
 *
 * ⚠️ **NOTHING SEEDS A PUBLISHED LEGAL DOCUMENT, ON PURPOSE** — `legal:seed`
 * writes unreviewed v0.9 drafts and publication is counsel's act — and
 * `SignupTerms` refuses to open an account without one. So every test that
 * registers a tenant through either door needs this first, and it is declared
 * once here rather than four times across the files that register (694 and 808's
 * zero-byte run was a duplicated global helper name).
 *
 * ⚠️ **IT ASSERTS ITS OWN POSTCONDITION**, `loadEveryRequiredRegister()`'s way:
 * a fixture that quietly published nothing would leave every registration test
 * refused for a reason it never mentions, which reads as a broken harness.
 *
 * @return array<value-of<LegalDocumentType>, LegalDocument>
 */
function publishSignupTerms(string $version = '1.0'): array
{
    foreach (SignupTerms::DOCUMENTS as $type) {
        LegalDocument::factory()->ofType($type)->published()->create(['version' => $version]);
    }

    $published = app(SignupTerms::class)->current();

    if ($published === null) {
        throw new RuntimeException(
            'publishSignupTerms() left SignupTerms::current() answering null. Every document '
            .'in SignupTerms::DOCUMENTS needs a published version, or no account can be opened.',
        );
    }

    return $published;
}

/**
 * How many things on a rendered page a person can actually press.
 *
 * ⛔ **THIS EXISTS BECAUSE A WIZARD STEP SHIPPED WITH NONE** (9156). Setup step
 * 3 rendered a progress bar whose `<li>`s are not links, inside a layout that
 * carries no site navigation on purpose — so the page had no next, no skip, no
 * back and nothing to click, and every instrument in the repository was green.
 * `assertSee('Continue')` would not have caught it either: the word appears in
 * a sentence as readily as on a button.
 *
 * ⚠️ **READ AS A TREE, NOT AS A STRING** — `ReviewSignTest`'s rule, one screen
 * over, and for the same reason: a substring cut anywhere near an attribute
 * puts half a control on the wrong side of the boundary, and a looser cut
 * passes for a reason that has nothing to do with the property.
 *
 * ⚠️ **A `<button>` OR AN `<a href>` AND NOTHING ELSE.** An `<a>` with no
 * `href` is not a link and is not reachable by keyboard; `<input type=submit>`
 * is deliberately not counted, because this application's one submit control
 * ({@see resources/views/components/ui/submit.blade.php}) renders a `<button>`
 * and counting a shape nothing here produces would be an arm that can never
 * fire (256).
 *
 * ⚠️ **IT COUNTS RATHER THAN ASSERTS**, so a caller can say what it means: a
 * step must have at least one, and `ReviewSignTest` needs the opposite claim
 * about the inside of a printed card.
 */
function pressableControlCount(string $html): int
{
    $document = new DOMDocument;

    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($document);

    $buttons = $xpath->query('//button');
    $links = $xpath->query('//a[@href]');

    return ($buttons === false ? 0 : $buttons->length)
        + ($links === false ? 0 : $links->length);
}

/**
 * The URL out of the most recent magic-link email.
 *
 * ⚠️ **DECLARED HERE RATHER THAN IN A TEST FILE**, `publishSignupTerms()`'s
 * reason: the magic link is one of four sign-in doors and more than one suite
 * has to walk it, and a second copy under a second name is how 694 and 808's
 * zero-byte run happened. It lived in `tests/Feature/Auth/LoginMethodsTest.php`
 * until 9156.
 *
 * The plaintext token exists only in the email — the stored copy is a SHA-256
 * hash — so reconstructing the journey means taking the URL out of the message,
 * exactly as a person takes it out of their inbox.
 */
function magicLinkUrlFromMail(): string
{
    $url = null;

    Notification::assertSentOnDemand(
        MagicLinkLogin::class,
        function (MagicLinkLogin $notification, array $channels, object $notifiable) use (&$url): bool {
            $mail = $notification->toMail($notifiable);

            $url = $mail->actionUrl;

            return true;
        },
    );

    expect($url)->toBeString();

    return (string) $url;
}

function magicLinkTokenFromMail(): string
{
    return basename(parse_url(magicLinkUrlFromMail(), PHP_URL_PATH) ?: '');
}

if (! function_exists('toastCarrying')) {
    /** a Pest-style toast matcher shared by admin screen tests */
    function toastCarrying(string $type, string $contains): callable
    {
        return fn (string $name, array $params): bool => ($params['type'] ?? null) === $type
            && is_string($params['message'] ?? null)
            && str_contains($params['message'], $contains);
    }
}
