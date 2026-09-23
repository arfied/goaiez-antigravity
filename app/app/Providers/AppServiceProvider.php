<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Agent\AgentThreads;
use App\Contracts\Campaigns\CampaignContextResolver;
use App\Contracts\CmsAdapter;
use App\Contracts\FetchGateway;
use App\Contracts\GeoIpLookup;
use App\Contracts\IndexNowKeys;
use App\Contracts\L0Archive;
use App\Contracts\Links\LinkRegistry;
use App\Contracts\MessageSender;
use App\Contracts\PlacesClient;
use App\Contracts\SearchConsoleClient;
use App\Contracts\SendLogReader;
use App\Contracts\Texter;
use App\Contracts\Transcriber;
use App\Contracts\VoiceProvider;
use App\Enums\OauthProvider;
use App\Enums\OutreachChannel;
use App\Http\Middleware\TenantRole;
use App\Livewire\Account\ReplyExamples as AccountReplyExamples;
use App\Livewire\Account\ReviewRules as AccountReviewRules;
use App\Services\ActivityService;
use App\Services\Actuation\LogCmsAdapter;
use App\Services\Actuation\WordPress\WordPressAdapter;
use App\Services\Agent\AgentThreadStates;
use App\Services\Audit\AuditContextBuilder;
use App\Services\Audit\AuditEngine;
use App\Services\Audit\Checks\GbpCompletenessCheck;
use App\Services\Audit\Checks\NapQuickScanCheck;
use App\Services\Audit\Checks\ReviewStatsCheck;
use App\Services\Audit\Checks\SiteBasicsCheck;
use App\Services\Billing\MessageCostLedger;
use App\Services\Billing\MessageRates;
use App\Services\Billing\SendCredits;
use App\Services\Campaigns\CampaignReplyResolver;
use App\Services\Config\CredentialStore;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\IdentifierHashEpochs;
use App\Services\Fetch\DirectFetchGateway;
use App\Services\Fetch\RobotsPolicy;
use App\Services\Gsc\GoogleSearchConsoleClient;
use App\Services\Indexing\UnhostedIndexNowKeys;
use App\Services\Links\TenantLinks;
use App\Services\Mail\GmailApiClient;
use App\Services\Mail\GmailApiTransport;
use App\Services\Mail\MailSettlement;
use App\Services\Mail\PlatformMailContext;
use App\Services\Messaging\Outbound\PlatformMessageSender;
use App\Services\Messaging\Outbound\SendSettlement;
use App\Services\Messaging\Outbound\SmsSendDriver;
use App\Services\Messaging\SendingGuard;
use App\Services\Oauth\GoogleTokenRefresher;
use App\Services\Oauth\MetaTokenRefresher;
use App\Services\Oauth\MicrosoftTokenRefresher;
use App\Services\Oauth\TokenService;
use App\Services\Ops\ScheduledRunMeter;
use App\Services\Pixel\NullGeoIpLookup;
use App\Services\Places\GooglePlacesClient;
use App\Services\Places\PlacesSpend;
use App\Services\Sms\InfobipClient;
use App\Services\Sms\LogTexter;
use App\Services\Sms\PlatformTexter;
use App\Services\Voice\InfobipVoiceProvider;
use App\Services\Voice\NullTranscriber;
use App\Services\Voice\NullVoiceProvider;
use App\Services\Warehouse\ObjectStoreL0Archive;
use App\Support\ActuationRateLimits;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\CreditGrantAccess;
use App\Support\Admin\LifecycleAccess;
use App\Support\Admin\SupportAccess;
use App\Support\ChatRateLimits;
use App\Support\FeedbackRateLimits;
use App\Support\LegalDocumentRateLimits;
use App\Support\MeRateLimits;
use App\Support\PixelRateLimits;
use App\Support\PublicAuditRateLimits;
use App\Support\ShortLinkRateLimits;
use App\Support\UnsubscribeRateLimits;
use App\Support\WidgetRateLimits;
use Closure;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Schema commands, which need DDL and therefore the owner role.
     *
     * `migrate:status` and `migrate:install` only read or touch the migrations
     * table, but they are included so every command in this family behaves the
     * same way — a family where one member differs is worse than one where none
     * do.
     *
     * @var list<string>
     */
    private const SCHEMA_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:install',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'migrate:status',
        'db:wipe',
        'schema:dump',
    ];

    public function register(): void
    {
        $this->registerCredentialStore();
        $this->registerTokenVault();
        $this->registerPlacesClient();
        $this->registerSearchConsoleClient();
        $this->registerFetchGateway();
        $this->registerAuditEngine();
        $this->registerTexter();
        $this->registerVoice();
        $this->registerCmsAdapter();
        $this->registerIndexNowKeys();
        $this->registerMessageSender();
        $this->registerPlatformMailContext();
        $this->registerIdentifierHashEpochs();
        $this->registerAgentThreads();
        $this->registerLinkRegistry();
        $this->registerCampaignContextResolver();
        $this->registerL0Archive();
        $this->registerGeoIpLookup();
        $this->registerScheduledRunMeter();
        $this->silenceCashiersOwnRoutes();
    }

    /**
     * ⚠️ **A SINGLETON BECAUSE IT HOLDS STATE BETWEEN TWO EVENTS.**
     * {@see ScheduledRunMeter} remembers, for the length of one `schedule:run`
     * invocation, whether *this* process opened an entry's marker — a fact read
     * a few microseconds later when the same entry finishes. Resolved fresh per
     * event, that answer would be lost every time and the overlapping-launch
     * bell could never ring. It is also why the binding is here rather than
     * left to the container's default autowiring, which returns a new instance
     * on every `make()`.
     */
    private function registerScheduledRunMeter(): void
    {
        $this->app->singleton(ScheduledRunMeter::class);
    }

    /**
     * The landing layer — `GOAIEZ_PIXEL_MASTER_BUILD` §5.2.
     *
     * Bound to an interface because CLAUDE.md §"Pixel / warehouse" asks for it by
     * name: the Cloudflare Workers collector and ClickHouse are deferred, and
     * *"keep ingest behind an interface so re-splitting is a swap, not a
     * rewrite."* This is that seam.
     *
     * NOT a singleton, on `TenantLinks`' reasoning: it holds no state, and the
     * disk it writes to is read from config on every call so a test can point it
     * at a fake without the archive knowing.
     *
     * ⛔ **NOTHING IN A REQUEST PATH RESOLVES THIS** (decision 4861). The
     * collector that would write L0 is unbuilt and `PixelTest`'s ingest tripwire
     * still passes; the only caller is the replay command and the tests.
     */
    private function registerL0Archive(): void
    {
        $this->app->bind(L0Archive::class, ObjectStoreL0Archive::class);
    }

    /**
     * §11 row 8's geo/ASN seam (decision 5000s) — see `App\Contracts\GeoIpLookup`.
     *
     * ⛔ **BOUND TO THE NULL IMPLEMENTATION BECAUSE THAT IS THE ONLY ONE THAT
     * EXISTS.** No GeoLite2 database is available in this environment, so
     * binding anything else here would be inventing a vendor call. Nothing
     * resolves this today — the geo/ASN columns it would feed are deliberately
     * not on the derived event table either, on the same reasoning
     * `registerL0Archive()`'s
     * docblock gives for its own "nothing resolves this" line.
     */
    private function registerGeoIpLookup(): void
    {
        $this->app->bind(GeoIpLookup::class, NullGeoIpLookup::class);
    }

    /**
     * Which send an inbound reply is answering (T176 P20, R20).
     *
     * NOT a singleton, on `TenantLinks`' reasoning and with the same hazard: it
     * resolves a tenant from our own receiving number and then reads inside it,
     * so an instance shared across a queue worker's jobs would be an object
     * outliving the tenant that resolved it. It holds no state worth sharing —
     * every call re-derives the tenant from the message it was handed.
     *
     * ⚠️ **THE WRITER IS NOT BOUND HERE AND IS DELIBERATELY NOT AN INTERFACE.**
     * {@see CampaignReplies} is what records a linkage, it is reached
     * concretely, and it has no contract on purpose: three lanes need to *ask*
     * which send a reply answers, and exactly one place — the carrier webhook —
     * may *decide* it.
     */
    private function registerCampaignContextResolver(): void
    {
        $this->app->bind(CampaignContextResolver::class, CampaignReplyResolver::class);
    }

    /**
     * Where the assistant stands on a thread — T176 P3, rails 3 and 4.
     *
     * NOT a singleton, on `TenantLinks`' reasoning and for a sharper version of
     * its second point: this object holds no state worth sharing, and an
     * instance living longer than the tenant that resolved it would be a
     * boundary hazard on the one class that decides whether a model may speak to
     * somebody's customer. Every method calls `Tenancy::idOrFail()` afresh.
     *
     * ⛔ **`UnbuiltAgentThreads` IS DELETED, NOT MERELY UNBOUND.** The docblock
     * above says why, and `Feature/Agent/ThreadStateTest` asserts the file is
     * gone while `Architecture/AgentTest` fails the build if a second
     * implementation of this contract ever appears.
     */
    private function registerAgentThreads(): void
    {
        $this->app->bind(AgentThreads::class, AgentThreadStates::class);
    }

    /**
     * What links a business has given its assistant (T176 P6, R13/R14).
     *
     * NOT a singleton, on `PlacesClient`'s and the texter's reasoning: it holds
     * no state worth sharing, and every read is a scoped query whose answer must
     * move the moment the owner saves a new link on the screen one route over.
     *
     * ⚠️ **AND A SINGLETON WOULD BE A TENANT-BOUNDARY HAZARD RATHER THAN MERELY
     * STALE**, which is why this note is here and not only in the class: an
     * instance shared across a queue worker's jobs is an object living longer
     * than the tenant that resolved it. Every method calls `Tenancy::idOrFail()`
     * afresh for the same reason.
     */
    private function registerLinkRegistry(): void
    {
        $this->app->bind(LinkRegistry::class, TenantLinks::class);
    }

    /**
     * Whether this install can still read the hashes it has stored (8080).
     *
     * ⚠️ **`scoped()`, WHICH IS NEITHER OF THE TWO OBVIOUS CHOICES, AND THE
     * REASON IS A QUEUE WORKER.** The object memoises the live epoch set so that
     * `ConsentService::decide()` does not re-read a one-row table per recipient
     * inside a campaign loop — `RunCampaignJob` resolves the service per
     * contact, so a plain `bind` would mean two extra round trips for every
     * person in a five-thousand-name audience. A `singleton` would fix that and
     * hold the memo for the **life of the worker**, which is hours: an operator
     * who retired a superseded epoch would go on being refused until somebody
     * restarted Horizon, and nothing would say why. `scoped()` is reset between
     * jobs, so the memo lasts exactly one request or one job.
     *
     * ⚠️ **THE STALE DIRECTION IS THE SAFE ONE EITHER WAY**, which is what makes
     * a memo acceptable at all here: a held set can only make this refuse a send
     * it should have made, never make one it should have refused.
     */
    private function registerIdentifierHashEpochs(): void
    {
        $this->app->scoped(IdentifierHashEpochs::class);
    }

    /**
     * The one object that says which identity a message is being sent under.
     *
     * ⚠️ **A SINGLETON, AND IT HAS TO BE.** `PlatformMailer::deliverNow()`
     * establishes the identity and the `MessageSending` listener reads it; two
     * instances would mean the listener reading an object nobody set, and the
     * symptom would be a customer-facing message going out with the platform's
     * own name and no reply route — a silent loss of the tracking code rather
     * than an error. The class's own docblock argues why a mutable object is the
     * least-bad of four options.
     */
    private function registerPlatformMailContext(): void
    {
        $this->app->singleton(PlatformMailContext::class);
    }

    /**
     * Which driver carries a text message (row 4 slice 1, `BUILD-PLAN` §2.10.3)
     * — and, since 7362, which one is asked what became of it afterwards.
     *
     * NOT a singleton, for `PlacesClient`'s reason: neither driver holds state
     * worth sharing, and a long-lived instance would outlive a credential
     * rotation performed in Ops.
     *
     * ⚠️ **AN UNKNOWN DRIVER THROWS AND MUST NEVER FALL BACK TO `log`.** A
     * fallback would mean a typo in `SMS_DRIVER` silently stops every message in
     * production while every log line, every job and every screen reports a
     * healthy send — this codebase's most-recorded failure shape, on the channel
     * where the recipient is somebody else's customer. It throws at resolution
     * rather than at boot deliberately: the app still boots, so the screen an
     * operator would use to see what is wrong is still up.
     *
     * ⚠️ **AND A DRIVER IS NOT PERMISSION TO SEND.** {@see PlatformTexter}'s
     * `sms.enabled` switch seeds false and waits on slice 2's STOP handling
     * (1567); naming the Infobip driver here sends nothing on its own.
     */
    private function registerTexter(): void
    {
        $this->app->bind(Texter::class, fn (): Texter => $this->smsDriver());

        // ⛔ **THE SAME DRIVER ANSWERS BOTH CONTRACTS, AND IT HAS TO BE THE
        // SAME ONE** (7362). `SendLogReader` asks a vendor what became of a
        // handle *this application put on that vendor's wire*, so a reader
        // resolved from a different driver than the sender would be asking
        // Infobip about a message the log driver invented, or asking nobody
        // about a message Infobip is holding. One `SMS_DRIVER` chooses both,
        // through one expression, so the two cannot be configured apart.
        //
        // ⚠️ **AND IT IS STILL TWO BINDINGS RATHER THAN AN ALIAS.** An alias
        // would make a caller who holds a `SendLogReader` able to resolve the
        // same object as a `Texter`; the whole point of the second interface is
        // that reading the log is not permission to send, and the lint in
        // `MessagingTest` enumerates each contract's callers separately.
        $this->app->bind(SendLogReader::class, fn (): SendLogReader => $this->smsDriver());
    }

    /**
     * The one SMS driver this deployment is configured for.
     *
     * NOT a singleton, for `PlacesClient`'s reason: neither driver holds state
     * worth sharing, and a long-lived instance would outlive a credential
     * rotation performed in Ops. Two resolutions therefore produce two objects,
     * which is fine — neither carries anything the other needs to see.
     */
    private function smsDriver(): InfobipClient|LogTexter
    {
        $driver = config('services.sms.driver');

        if ($driver === 'log') {
            return new LogTexter;
        }

        if ($driver === 'infobip') {
            return new InfobipClient;
        }

        throw new InvalidArgumentException(
            'Unknown SMS driver ['.(is_scalar($driver) ? (string) $driver : gettype($driver))
            .']. Set SMS_DRIVER to log or infobip; there is deliberately no fallback.'
        );
    }

    /**
     * Which vendor answers a call, and who turns a voicemail into words
     * (T176 P2, voice call forwarding, voice driver architecture).
     *
     * ⛔ **BOTH DEFAULTS REACH NOBODY, AND BOTH ARE THE SHIPPED BEHAVIOUR RATHER
     * THAN A PLACEHOLDER.** `VOICE_DRIVER` seeds `null` because Infobip
     * Voice/Calls is not activated on the account (T176 §7 item 3), and the
     * transcriber has no vendor at all because that decision is open
     * (`CLAUDE.md`: Whisper against Deepgram, *"still open — decide at Stage
     * 6b"*). Naming a vendor here to fill a gap would pick a subprocessor in a
     * slice about telephony.
     *
     * ⚠️ **AN UNKNOWN VOICE DRIVER THROWS**, for {@see self::registerTexter()}'s
     * reason exactly: a typo that silently fell back to `null` would stop every
     * missed-call text-back while every screen reported a healthy system.
     *
     * ⚠️ **THE TRANSCRIBER HAS NO SUCH THROW BECAUSE IT HAS NO CONFIGURATION
     * KEY.** There is one implementation and nothing to mistype; the day a
     * vendor is chosen it gains a key and this method gains the same guard.
     */
    private function registerVoice(): void
    {
        $this->app->bind(VoiceProvider::class, function (): VoiceProvider {
            $driver = config('services.voice.driver');
            Log::info('VoiceProvider resolved with driver: '.var_export($driver, true));

            if ($driver === 'null') {
                return new NullVoiceProvider;
            }

            if ($driver === 'infobip') {
                return new InfobipVoiceProvider;
            }

            throw new InvalidArgumentException(
                'Unknown voice driver ['.(is_scalar($driver) ? (string) $driver : gettype($driver))
                .']. Set VOICE_DRIVER to null or infobip; there is deliberately no fallback.'
            );
        });

        $this->app->bind(Transcriber::class, NullTranscriber::class);
    }

    /**
     * The seam every website this platform edits sits behind — doc `41` Part 2.
     *
     * ⚠️ **TWO DRIVERS EXIST AND THE DEFAULT STILL WRITES TO NO WEBSITE.**
     * `log` is slice A's deliverable and stays the seed everywhere; `wordpress`
     * is slice F1's and reaches a tenant's own site over core REST with an
     * owner-pasted Application Password.
     *
     * ⛔ **THIS COMMENT SAID `wordpress` ARRIVES WITH SLICE G AND THAT WAS THE
     * PLAN BEFORE §2.11.5 CONFLICT 7 SPLIT SLICE F** (5580). The adapter lands
     * in F1; what G still owns is the **activation** — `actuation.enabled`, the
     * connect screen and the staging checklist against a real install. Building
     * the driver and turning it on are two acts and only the second is G's.
     *
     * ⚠️ **SO SETTING `CMS_DRIVER=wordpress` ALONE ACTUATES NOTHING**, exactly
     * as `SMS_DRIVER` is not `sms.enabled`. It is the deployment switch; the
     * operational one is the registry, it seeds false, and it names slice H as
     * what it waits for.
     *
     * ⚠️ **AN UNKNOWN DRIVER THROWS**, for {@see self::registerVoice()}'s reason
     * and one sharper: this is the key whose mistake, now that a live adapter
     * exists, is answered by writing to somebody else's property.
     */
    private function registerCmsAdapter(): void
    {
        $this->app->bind(CmsAdapter::class, function (): CmsAdapter {
            $driver = config('services.cms.driver');

            if ($driver === 'log') {
                return new LogCmsAdapter;
            }

            if ($driver === 'wordpress') {
                return $this->app->make(WordPressAdapter::class);
            }

            throw new InvalidArgumentException(
                'Unknown CMS adapter driver ['.(is_scalar($driver) ? (string) $driver : gettype($driver))
                .']. Set CMS_DRIVER to log or wordpress; there is deliberately no fallback.'
            );
        });
    }

    /**
     * Where a location's IndexNow key file lives.
     *
     * ⛔ **ONE IMPLEMENTATION, AND IT NEVER RETURNS A KEY** (5680). IndexNow
     * verifies ownership by fetching a text file from the tenant's own host, and
     * the only thing this platform can put on a tenant's WordPress today is a
     * post, a page or a media upload over core REST — where a key file binds to
     * `/wp-content/uploads/` and can submit uploads and nothing else (5581).
     * Serving the site root is the plugin's job (F2).
     *
     * ⚠️ **BOUND RATHER THAN DEFAULTED, SO THE DAY IT CHANGES IS A DAY SOMEBODY
     * WROTE A LINE.** `ActuationTest` asserts this build still resolves
     * {@see UnhostedIndexNowKeys}: a slice that binds a real provider without
     * the file behind it would have us POST a key that nothing serves, which
     * IndexNow answers with a 403 — *"key not found, file found but key not in
     * the file"* — and which, from the rows alone, looks exactly like a vendor
     * outage.
     */
    private function registerIndexNowKeys(): void
    {
        $this->app->bind(IndexNowKeys::class, UnhostedIndexNowKeys::class);
    }

    /**
     * The one thing a feature calls to send a message on any channel
     * (decision 2540).
     *
     * ⛔ **THIS BINDING IS THE POINT OF THE SLICE, AND ITS ABSENCE WAS DECISION
     * 2206.** `App\Contracts\MessageSender` and `App\Contracts\SendDriver`
     * shipped with their value objects, their guarantees and their tests, and
     * with **nothing bound to either** — so `RunCampaignJob`'s
     * `app(MessageSender::class)->send()` threw `BindingResolutionException` the
     * moment a real queue ran it, while every test passed because every test
     * binds a recording fake. An interface with no implementation is decision
     * 272's shape wearing a contract's clothes.
     *
     * ⚠️ **THE DRIVER MAP IS BUILT HERE AND IS DELIBERATELY NOT A SCAN.**
     * Discovering `SendDriver` implementations by reflection would mean a class
     * added anywhere in `app/` silently becomes a way to reach a carrier, and
     * *"which channels can this platform send on"* would have no single answer
     * to read. It is a literal map, so adding a channel is a visible edit in the
     * file a reviewer already checks for bindings.
     *
     * ⛔ **THERE IS NO EMAIL DRIVER, AND THAT IS STATED RATHER THAN IMPLIED**
     * (2553). `PlatformMailer` sends email today through `ReviewInviteSender`'s
     * own path, which predates this contract and is untouched; wiring it in
     * behind `SendDriver` would put every existing email send through a credit
     * debit and a cost book in a slice whose brief was SMS. A message composed
     * for email reaches `PlatformMessageSender` and gets a `LogicException`
     * naming the missing driver — loud, on the developer's own path, rather than
     * a refusal filed against the recipient.
     *
     * NOT a singleton, for `registerTexter()`'s reason one layer down: the
     * drivers it holds resolve a `Texter` that must not outlive a credential
     * rotation performed in Ops.
     */
    private function registerMessageSender(): void
    {
        $this->app->bind(SmsSendDriver::class, fn ($app): SmsSendDriver => new SmsSendDriver(
            $app->make(PlatformTexter::class),
        ));

        $this->app->bind(MessageSender::class, fn ($app): MessageSender => new PlatformMessageSender(
            guard: $app->make(SendingGuard::class),
            credits: $app->make(SendCredits::class),
            costs: $app->make(MessageCostLedger::class),
            rates: $app->make(MessageRates::class),
            settlement: $app->make(SendSettlement::class),
            drivers: [
                OutreachChannel::Sms->value => $app->make(SmsSendDriver::class),
            ],
        ));
    }

    /**
     * Turn off the two routes Cashier registers for itself.
     *
     * ⚠️ **THEY HAVE BEEN LIVE AND UNAUTHENTICATED SINCE STAGE 0** (decision
     * 683). `laravel/cashier` has been a dependency since the first install, and
     * `CashierServiceProvider::registerRoutes()` publishes `POST stripe/webhook`
     * and `GET stripe/payment/{id}` unless this static is set — which nothing
     * ever set. `php artisan route:list` on `main` shows both.
     *
     * They are harmless today only by accident: `Cashier::findBillable()` needs
     * a model using its `Billable` trait and no model does, so the handler finds
     * nothing and returns. That accident is one trait away from ending, and what
     * would end it is the very change decision 581 planned — at which point a
     * webhook handler nobody in this repository wrote would begin acting on our
     * subscription rows, on a URL nobody chose, running Cashier's own
     * cancellation and payment-method logic against a schema that is not
     * Cashier's (581).
     *
     * ⚠️ **IN `register()`, NOT `boot()`.** Package providers boot before the
     * application's own, so setting this in `boot()` would run after the routes
     * were already registered and would do nothing at all — while looking
     * exactly like it had worked.
     *
     * The endpoint this application actually serves is `POST /webhooks/stripe`,
     * which `BUILD-PLAN` §3 names, and a test asserts Cashier's two are absent.
     */
    private function silenceCashiersOwnRoutes(): void
    {
        Cashier::ignoreRoutes();
    }

    /**
     * The Credentials Manager (`38` Part 1, D-149).
     *
     * A SINGLETON, AND THAT IS THE FEATURE. `38` Part 1 asks for "a cached
     * accessor with instant bust on rotate", and the cache is a private array on
     * this one instance — deliberately not `Cache::`, because production runs
     * `CACHE_STORE=database` (decision 415) and putting a decrypted vendor secret
     * through the cache would write every key in plaintext into a table with no
     * encryption, no rotation and no audit. A per-process memo cannot outlive the
     * request, which is what makes the bust structurally instant.
     *
     * The opposite of `PlacesClient` below, and for the mirror-image reason: a
     * long-lived spend gate would keep spending against a stale budget, whereas a
     * long-lived credential reader is the only way one query per key per request
     * does not become one per vendor call.
     */
    private function registerCredentialStore(): void
    {
        $this->app->singleton(CredentialStore::class);
    }

    public function boot(): void
    {
        $this->routeSchemaCommandsToTheOwnerRole();
        $this->forbidLiveVendorCallsInTests();
        $this->registerNestableLivewireComponents();
        $this->registerGmailTransport();
        $this->applyPlatformMailIdentity();
        $this->settleSentMail();
        $this->meterScheduledRuns();

        AdminAccess::register();
        SupportAccess::register();
        LifecycleAccess::register();
        CreditGrantAccess::register();
        PublicAuditRateLimits::register();
        FeedbackRateLimits::register();
        LegalDocumentRateLimits::register();
        WidgetRateLimits::register();
        ShortLinkRateLimits::register();
        UnsubscribeRateLimits::register();
        MeRateLimits::register();
        PixelRateLimits::register();
        ActuationRateLimits::register();
        ChatRateLimits::register();

        Livewire::addPersistentMiddleware([TenantRole::class]);
    }

    /**
     * The Gmail API transport (email delivery architecture, decision 2093).
     *
     * ⚠️ **`Mail::extend` RATHER THAN A SERVICE CALL AT THE SEND SITE, WHICH IS
     * WHAT MAKES THE SEAM REAL.** Registering a transport means `PlatformMailer`,
     * `DeliverPlatformMail`, every notification and every test that fakes mail
     * are untouched by which vendor carries the message — and slotting SES in is
     * `MAIL_MAILER=smtp` with SES's credentials, no code at all, because SES is
     * SMTP. A seam asserted in a docblock is 314-316's shape; this is the
     * mechanism the assertion rests on.
     *
     * ⚠️ **THE CLIENT IS RESOLVED PER TRANSPORT AND NOT SHARED.** It holds a
     * cached access token in the cache rather than on itself, so there is no
     * state worth sharing and a long-lived instance would outlive a credential
     * rotation performed in Ops — `registerTexter()`'s reasoning, and
     * `PlacesClient`'s before it.
     *
     * ⚠️ **THE MANAGER RATHER THAN THE `Mail` FACADE, AND THAT IS NOT STYLE.**
     * The first version of this method called `Mail::extend()`, which reddened
     * `MailTest`'s "only the platform mailer sends email" chokepoint — and the
     * rule was right to fire even though registering a transport cannot send
     * anything. The fix that suggests itself is to permit the facade here; that
     * is the wrong one, because the lint's *import* clause is what closes the
     * aliasing hole its own docblock describes (`use ... Mail as M; M::to()`),
     * and exempting this file would open it in the largest file in `app/`.
     * Resolving the binding needs no import, so the chokepoint is untouched and
     * `PlatformMailer` remains the only name that may reach the facade.
     *
     * `callAfterResolving` rather than `make`, so the manager is not
     * instantiated during boot merely to be told about a driver — it fires
     * immediately if something has already resolved it, and on first resolution
     * otherwise.
     */
    private function registerGmailTransport(): void
    {
        $this->callAfterResolving('mail.manager', function (mixed $manager): void {
            if (! $manager instanceof MailManager) {
                return;
            }

            $manager->extend(
                'gmail',
                fn (): GmailApiTransport => new GmailApiTransport(new GmailApiClient),
            );
        });
    }

    /**
     * Put the tenant's name and the reply code on the message about to leave.
     *
     * ⚠️ **A LISTENER RATHER THAN NINE NOTIFICATION CLASSES REMEMBERING**, and
     * the one that forgot would send a customer-facing message with no reply
     * route — which still sends, and therefore says nothing. `PlatformMailContext`
     * leaves a message alone when no identity was established, so platform mail
     * to an account holder is untouched by this.
     */
    private function applyPlatformMailIdentity(): void
    {
        Event::listen(function (MessageSending $event): void {
            $this->app->make(PlatformMailContext::class)->apply($event);
        });
    }

    /**
     * Write down the handle the transport gave a message it accepted (6361).
     *
     * ⛔ **THIS IS THE ONLY MOMENT THE HANDLE EXISTS, AND WITHOUT IT THE EMAIL
     * COMPLAINT TRIP CAN NEVER FIRE** (2496's shape, on the channel R16 made
     * primary). `PlatformMailer::deliverNow()` sends through `notifyNow()`,
     * which returns nothing, so `SentMessage::getMessageId()` is reachable only
     * from this event — and that id is what every SES bounce, complaint and
     * delivery names the message by.
     *
     * ⚠️ **A LISTENER FOR `applyPlatformMailIdentity()`'s OWN REASON**, and the
     * two are deliberately adjacent: the identity established for the send is
     * still current when this fires, because `PlatformMailContext::during()`
     * wraps the whole delivery. {@see MailSettlement}
     * leaves a message alone when no identity was established, so platform mail
     * to an account holder is untouched.
     *
     * ⚠️ **IT MUST NOT THROW, AND THE SERVICE IS WRITTEN NOT TO.** By the time
     * this runs the message has gone; failing here would fail a queued job for
     * mail the customer already has, and the retry would send it twice.
     */
    private function settleSentMail(): void
    {
        Event::listen(function (MessageSent $event): void {
            $this->app->make(MailSettlement::class)->record($event->sent);
        });
    }

    /**
     * Time every scheduled command against its own overlap window (7080–7099).
     *
     * ⛔ **THE ONLY WRITER OF A SCHEDULED COMMAND'S RUNTIME IN THIS
     * APPLICATION, AND BEFORE THIS THERE WAS NONE.** Decision 6975 named the
     * gap: wave 6 argued thirty-five `withoutOverlapping()` windows from what
     * each command *does*, and nothing has ever recorded what one *has done* —
     * so the failure that change can introduce, a lock expiring under a
     * still-running process, was the one failure nothing here could report.
     * {@see ScheduledRunMeter} carries the full argument, including why
     * `ScheduledTaskFinished::$runtime` alone would have measured a fork rather
     * than a six-hour sweep for thirty of the thirty-five.
     *
     * ⚠️ **FOUR LISTENERS, AND EACH ONE IS LOAD-BEARING.** `Starting` opens the
     * marker that makes an overlapping *launch* visible; `Finished` is the
     * foreground duration and the background launch; `ScheduledBackgroundTaskFinished`
     * is the only moment a detached entry's true duration exists, and it fires
     * in the `schedule:finish` subprocess rather than here; `Failed` takes the
     * marker back when `Event::run()` threw and no finish is coming.
     *
     * ⚠️ **GATED ON `runningInConsole()`.** These four events are dispatched by
     * `ScheduleRunCommand` and `ScheduleFinishCommand` and by nothing else, so
     * outside the console they are four closures that can never fire — and the
     * gate says so where a reader of the web path will see it. ⚠️ **The test
     * harness is a console process**, so this is exercised rather than skipped.
     *
     * ⚠️ **RESOLVED THROUGH A SINGLETON RATHER THAN CONSTRUCTED HERE**, for two
     * reasons: `boot()` runs on every request and eagerly building a meter that
     * pulls in the mailer and the texter would cost every one of them, and the
     * meter holds per-invocation state between `Starting` and `Finished` that a
     * fresh instance per event would throw away.
     */
    private function meterScheduledRuns(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(function (ScheduledTaskStarting $event): void {
            $this->withScheduledRunMeter(fn (ScheduledRunMeter $meter) => $meter->starting($event->task));
        });

        Event::listen(function (ScheduledTaskFinished $event): void {
            $this->withScheduledRunMeter(fn (ScheduledRunMeter $meter) => $meter->finished($event->task, $event->runtime));
        });

        Event::listen(function (ScheduledBackgroundTaskFinished $event): void {
            $this->withScheduledRunMeter(fn (ScheduledRunMeter $meter) => $meter->backgroundFinished($event->task));
        });

        Event::listen(function (Looping $event): void {
            Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
        });

        Event::listen(function (ScheduledTaskFailed $event): void {
            $this->withScheduledRunMeter(fn (ScheduledRunMeter $meter) => $meter->failed($event->task));
        });
    }

    /**
     * ⛔ **THE RESOLVE IS INSIDE THE NET, NOT OUTSIDE IT — R25.**
     * {@see ScheduledRunMeter} guards its own body, and that would still leave
     * one uncontained step: `make()` itself. A binding that cannot be built —
     * a cache store misconfigured at boot, a constructor dependency that throws
     * — would propagate out of the listener and into `ScheduleRunCommand`,
     * killing the scheduler tick that starts the campaign passes, the dunning
     * ticks and the renewal notices. **The platform stopped by its own
     * instrumentation is the worst version of the failure this feature exists
     * to report**, so the container call is wrapped too.
     *
     * @param  Closure(ScheduledRunMeter): void  $work
     */
    private function withScheduledRunMeter(Closure $work): void
    {
        try {
            $work($this->app->make(ScheduledRunMeter::class));
        } catch (Throwable $e) {
            Log::warning('the scheduled run meter could not be reached', [
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Give components that are rendered *inside* another component a tag-safe
     * name.
     *
     * ⚠️ **LIVEWIRE 4 CANNOT NEST A COMPONENT THAT LIVES IN A SUBDIRECTORY**,
     * and the way it fails is worse than a build error. Auto-discovery names
     * `App\Livewire\Account\ReviewRules` as `account.review-rules`, and
     * `SupportNestingComponents::getPreviouslyRenderedChild()` validates a child
     * tag against `/^[a-zA-Z][a-zA-Z0-9\-]*$/` — the dot fails it and the request
     * dies with *"Invalid Livewire child tag name"*, naming the parent's view
     * rather than the child.
     *
     * ⚠️ **THE FIRST RENDER IS FINE. THE FAILURE ARRIVES ON THE FIRST UPDATE**,
     * because that check runs only against previously rendered children. So a
     * nested subdirectory component looks completely correct until somebody
     * clicks something — here it would have been an owner pressing Pause
     * Everything and getting a 500 from a panel they had not touched. It was
     * caught by three unrelated pause and suspension tests, not by any test of
     * the component itself.
     *
     * An alias per nested component, rather than a blanket rename: the class
     * keeps the namespace its siblings use, and this list stays a readable
     * record of which components are nested at all.
     */
    private function registerNestableLivewireComponents(): void
    {
        Livewire::component('account-review-rules', AccountReviewRules::class);
        Livewire::component('account-reply-examples', AccountReplyExamples::class);
    }

    /**
     * Wire the token vault to its per-provider refreshers.
     *
     * The map is assembled here rather than resolved inside TokenService so that
     * "which providers can this vault refresh?" is one readable list rather than
     * a match statement buried in a method. A provider absent from it has no
     * refresh path, and the vault says so instead of guessing.
     */
    private function registerTokenVault(): void
    {
        $this->app->singleton(TokenService::class, fn ($app): TokenService => new TokenService(
            [
                OauthProvider::Google->value => new GoogleTokenRefresher,

                // ⚠️ SEARCH CONSOLE IS ITS OWN ROW, NOT A SCOPE ON `google`.
                // Same endpoint, same grant, same OAuth client — a different
                // consent, a different scope and an independently revocable
                // connection (decisions 1082-1083). Refreshing it under the
                // `google` label would put a Business Profile alarm on the ops
                // board when a Search Console grant broke, and send the owner to
                // reconnect the integration that was working.
                //
                // ⚠️ Its absence from this map would not fail loudly: the vault
                // would call the connection "a provider we never taught the vault
                // to refresh", mark it unusable and raise a Reconnect prompt —
                // for a grant that is perfectly fine and one HTTP call from being
                // renewed. That is what makes this line load-bearing rather than
                // tidy, and a test drives it.
                OauthProvider::Gsc->value => new GoogleTokenRefresher(OauthProvider::Gsc),

                OauthProvider::Microsoft->value => new MicrosoftTokenRefresher,
                // Meta's entry is an exchange, not a refresh — see the class.
                OauthProvider::Facebook->value => new MetaTokenRefresher,
            ],
            $app->make(ActivityService::class),
        ));
    }

    /**
     * Bind the Places contract to the Google implementation.
     *
     * Bound rather than resolved directly so the audit engine (slice E) can be
     * tested without a key and without a bill, and so row 3's in-tenant resolver
     * can swap the `purpose` label without a second client.
     *
     * NOT a singleton: PlacesSpend reads today's ledger on every gate check, and
     * a long-lived instance in a queue worker is exactly where a stale budget
     * would keep spending after the ceiling was reached.
     */
    private function registerPlacesClient(): void
    {
        $this->app->bind(PlacesClient::class, fn ($app): GooglePlacesClient => new GooglePlacesClient(
            $app->make(PlacesSpend::class),
            $app->make(DefaultsRegistry::class),
        ));
    }

    /**
     * Bind the Search Console contract to the Google implementation.
     *
     * Bound rather than resolved directly so the sync job and the Ops command
     * can be driven in tests without a tenant's OAuth token, a network call or a
     * quota — the same reason `PlacesClient` is bound, and the only reason: there
     * is no second source for Google's own search data, so this seam is for
     * testability rather than for a future vendor. Saying which matters, because
     * `GbpClient`'s docblock had to be corrected for claiming the opposite.
     *
     * NOT a singleton. It holds no state worth sharing, and a long-lived instance
     * in a queue worker is where a stale anything survives — the shape decision
     * 415's cache note and `PlacesClient`'s budget note both describe.
     */
    private function registerSearchConsoleClient(): void
    {
        $this->app->bind(
            SearchConsoleClient::class,
            fn ($app): GoogleSearchConsoleClient => new GoogleSearchConsoleClient(
                $app->make(TokenService::class),
            ),
        );
    }

    /**
     * Bind the one gateway every outbound page fetch goes through.
     *
     * `40` Part 6 makes this singular by design — "No module fetches HTML on its
     * own" — and ArchitectureTest enforces it as an import lint. Bound to the F0
     * implementation; F1-F3 are not built (BUILD-PLAN §2.5.2 slice D).
     */
    private function registerFetchGateway(): void
    {
        $this->app->bind(FetchGateway::class, fn ($app): DirectFetchGateway => new DirectFetchGateway(
            $app->make(RobotsPolicy::class),
        ));
    }

    /**
     * Assemble the audit engine from its four checks, in run order.
     *
     * THE LIST LIVES HERE RATHER THAN INSIDE THE ENGINE for the reason the
     * AuditCheck contract gives: adding or removing a check should be one line
     * in a place that reads like an inventory, not an edit to a match statement
     * inside the thing that runs them. `29` §6.2 describes its four checks as
     * "deterministic, cheap, honest" — three properties, none of which is
     * "fixed", and the two Places-backed ones are already narrowed by decisions
     * 196 and 212.
     *
     * The order is AuditCheckKey::inRunOrder() and it matters: the two checks
     * fed by data already paid for run before the two that wait on a stranger's
     * web server, so the first findings reach the page while the site fetch is
     * still in flight. AuditEngine persists after each one, which is what makes
     * that ordering visible to a polling client rather than merely tidy.
     *
     * NOT a singleton, for the same reason PlacesClient is not: the engine holds
     * a context builder holding a Places client whose budget gate reads today's
     * ledger, and a long-lived instance in a queue worker is where a stale
     * budget would keep spending past the ceiling.
     */
    private function registerAuditEngine(): void
    {
        $this->app->bind(AuditEngine::class, fn ($app): AuditEngine => new AuditEngine(
            $app->make(DefaultsRegistry::class),
            $app->make(AuditContextBuilder::class),
            [
                $app->make(GbpCompletenessCheck::class),
                $app->make(ReviewStatsCheck::class),
                $app->make(NapQuickScanCheck::class),
                $app->make(SiteBasicsCheck::class),
            ],
        ));
    }

    /**
     * Make it impossible for a test run to reach a real vendor.
     *
     * "Sandbox first" is only worth anything if it cannot be forgotten. A test
     * that neglects to fake an endpoint would otherwise send a real request with
     * real credentials — placing a call, sending a message, or spending quota —
     * and the failure would look like a flaky test rather than an incident.
     *
     * preventStrayRequests() refuses the request rather than sending it, which
     * is the load-bearing half and holds unconditionally. It covers every call
     * in app/Services because they all go through Laravel's HTTP client; a
     * client that built its own Guzzle instance would slip past this, which is
     * one of the reasons the token refreshers do not use Socialite's.
     *
     * ⛔ **THIS SAID "AN IMMEDIATE, LOUD FAILURE NAMING THE URL THAT WAS NOT
     * FAKED" AND THE LOUDNESS IS NOT A PROPERTY OF THIS CALL — MEASURED
     * 2026-08-25 (9350).** StrayRequestException extends RuntimeException, so
     * whether anything is heard depends entirely on what catches on the path.
     * Driven, with the exception's constructor instrumented across the whole
     * Unit/Feature suite: **three stray requests occur, and not one of them
     * fails a test.** BillingController's catch-all swallows one into a
     * redirect; RobotsPolicy's catch (Throwable) swallows the other two into a
     * fail-closed robots answer. The suite is green throughout.
     *
     * ⚠️ **AND IT IS NOT WHAT `Http::assertNothingSent()` READS EITHER**, which
     * is the mistake this sentence encouraged: 29 test bodies called
     * preventStrayRequests() as their arming call before an assertNothingSent()
     * that could not fail. The recorder is armed by Http::fake() alone. The
     * lint is ConventionsTest's "every Http::assertNothingSent() sits in a test
     * that armed the recorder".
     *
     * ⚠️ **THE REFUSAL IS STILL WORTH HAVING AND IS WHY THIS STAYS.** No real
     * credential leaves the process; what a reader may not conclude is that a
     * missed fake will announce itself.
     */
    private function forbidLiveVendorCallsInTests(): void
    {
        if ($this->app->runningUnitTests()) {
            Http::preventStrayRequests();
        }
    }

    /**
     * Point schema commands at the migration connection unless told otherwise.
     *
     * The default connection is `pgsql`, the runtime role, which deliberately
     * cannot create tables — that is what makes PostgreSQL row-level security
     * apply to it (decisions 131-134). So `php artisan migrate` fails with
     *
     *     SQLSTATE[42501]: permission denied for schema public
     *
     * which is correct, and tells you nothing about why or what to do instead.
     *
     * There is no case where a schema command should run as the runtime role:
     * it is not a preference, it is impossible. So rather than requiring
     * `--database=pgsql_migrate` on every invocation — and having `composer
     * setup` silently ship broken, which it did — the flag is supplied here.
     *
     * An explicit `--database` always wins, so the test harness (which passes
     * `pgsql_migrate` itself) and anyone deliberately targeting another
     * connection are unaffected.
     */
    private function routeSchemaCommandsToTheOwnerRole(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        $command = $argv[1] ?? null;

        if (! in_array($command, self::SCHEMA_COMMANDS, true)) {
            return;
        }

        // An explicit --database always wins. Checked against argv rather than
        // the parsed input because this runs during boot, before Symfony has
        // bound the input to the command's definition — the CommandStarting
        // event fires before that binding too, which is why the obvious
        // `$event->input->setOption()` approach silently does nothing.
        foreach ($argv as $argument) {
            if ($argument === '--database' || str_starts_with($argument, '--database=')) {
                return;
            }
        }

        config(['database.default' => 'pgsql_migrate']);
    }
}
