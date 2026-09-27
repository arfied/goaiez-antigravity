<?php

declare(strict_types=1);

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Account\CancelSubscriptionController;
use App\Http\Controllers\Account\InboundMediaController;
use App\Http\Controllers\Account\SelectLocationController;
use App\Http\Controllers\Account\TenantExportDownloadController;
use App\Http\Controllers\Account\TenantExportRequestController;
use App\Http\Controllers\Account\VoicemailRecordingController;
use App\Http\Controllers\Actuation\T3InjectionController;
use App\Http\Controllers\Auth\LoginPageController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\OauthLoginController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\Billing\AuthorizeNetCheckoutController;
use App\Http\Controllers\Billing\AuthorizeNetWebhookController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\CampaignMediaController;
use App\Http\Controllers\ChatScriptController;
use App\Http\Controllers\Content\HoldGrowthPageController;
use App\Http\Controllers\FeedbackPageController;
use App\Http\Controllers\FixThenAskCheckInController;
use App\Http\Controllers\Gbp\GbpConnectController;
use App\Http\Controllers\Gbp\ZernioWebhookController;
use App\Http\Controllers\Gsc\SearchConsoleConnectController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\IndustryPageController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\Mail\GmailPushController;
use App\Http\Controllers\Mail\SesFeedbackController;
use App\Http\Controllers\Mail\UnsubscribeController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\Pixel\PixelBundleController;
use App\Http\Controllers\ReviewHubController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Sms\InfobipDeliveryController;
use App\Http\Controllers\Sms\InfobipInboundController;
use App\Http\Controllers\SmsOptInController;
use App\Http\Controllers\Social\SocialConnectController;
use App\Http\Controllers\SuspendedAccountController;
use App\Http\Controllers\Voice\InfobipVoiceController;
use App\Http\Controllers\Whatsapp\WhatsappConnectController;
use App\Http\Controllers\WidgetScriptController;
use App\Http\Middleware\EnsureAdvancedDashboard;
use App\Http\Middleware\RequireIndustryPages;
use App\Http\Middleware\ResolveFeedbackPage;
use App\Livewire\Account\Activity as AccountActivity;
use App\Livewire\Account\AllScreens as AccountAllScreens;
use App\Livewire\Account\AssistantAnswers as AccountAssistantAnswers;
use App\Livewire\Account\AssistantLinks as AccountAssistantLinks;
use App\Livewire\Account\Calls as AccountCalls;
use App\Livewire\Account\Connections as AccountConnections;
use App\Livewire\Account\Credit as AccountCredit;
use App\Livewire\Account\CustomerProfile as AccountCustomerProfile;
use App\Livewire\Account\Customers as AccountCustomers;
use App\Livewire\Account\FacebookReviews as AccountFacebookReviews;
use App\Livewire\Account\Facts as AccountFacts;
use App\Livewire\Account\FollowUps as AccountFollowUps;
use App\Livewire\Account\Home as AccountHome;
use App\Livewire\Account\ImportCustomers;
use App\Livewire\Account\Inbox as AccountInbox;
use App\Livewire\Account\Knowledge as AccountKnowledge;
use App\Livewire\Account\Locations as AccountLocations;
use App\Livewire\Account\Messages as AccountMessages;
use App\Livewire\Account\PixelInstall as AccountPixelInstall;
use App\Livewire\Account\PlacesKey as AccountPlacesKey;
use App\Livewire\Account\Plan as AccountPlan;
// Aliased for the reason `Support` below is: `App\Services\Actuation\SiteChanges`
// is the change log this screen reads, and the two names differ only by
// namespace.
use App\Livewire\Account\ReplyQueue as AccountReplyQueue;
// Aliased: `Support` unqualified in this file would sit beside the whole
// `App\Livewire\Support` console namespace, and the two are opposite ends of
// one desk.
use App\Livewire\Account\Settings as AccountSettings;
use App\Livewire\Account\SiteChanges as AccountSiteChanges;
use App\Livewire\Account\Support as AccountSupport;
use App\Livewire\Account\Texting as AccountTexting;
use App\Livewire\Account\Visibility as AccountVisibility;
use App\Livewire\Account\WidgetInstall as AccountWidgetInstall;
use App\Livewire\Account\WinBack as AccountWinBack;
use App\Livewire\Admin\AccountAudit;
// Aliased for the same reason as LegalDocuments below: `Credentials` next to
// `CredentialStore` and `PlatformCredentials` in one file is three names for
// three different things, and the ambiguity is only ever resolved by luck.
use App\Livewire\Admin\AutomationRuns;
use App\Livewire\Admin\Credentials as CredentialsAdmin;
use App\Livewire\Admin\GbpGrantRevocations;
// Aliased: the component and the service it calls share a name, and the two
// appearing unqualified in one file is how the wrong one gets injected.
use App\Livewire\Admin\IndustryStartingPoints;
use App\Livewire\Admin\InternalUsers;
use App\Livewire\Admin\LegalDocumentIndex;
// Named for the screen rather than for the table, and deliberately not
// `OperatorAlerts`: the service that raises them already owns that name, and
// the two appearing unqualified in one file is how the wrong one gets injected.
use App\Livewire\Admin\LegalDocuments as LegalDocumentsAdmin;
use App\Livewire\Admin\LocationSettings;
use App\Livewire\Admin\MailSending;
use App\Livewire\Admin\NumberLookup;
use App\Livewire\Admin\OperatorAlertBoard;
use App\Livewire\Admin\OwnerChannelTexts;
use App\Livewire\Admin\OwnerNotifyConsents;
use App\Livewire\Admin\PhiTenants;
use App\Livewire\Admin\PlatformSettings;
use App\Livewire\Admin\ReviewQueue;
// Aliased for the same reason as LegalDocuments above: the screen and the
// service it reads through share a name, and the two appearing unqualified in
// one file is how the wrong one gets injected.
use App\Livewire\Admin\SendingControls;
use App\Livewire\Admin\StaffActivity;
use App\Livewire\Admin\TenantLocations;
use App\Livewire\Admin\TermsAcceptances as TermsAcceptancesAdmin;
use App\Livewire\Advanced\BroadcastComposer;
use App\Livewire\Advanced\Broadcasts;
use App\Livewire\Advanced\Changes;
// Aliased for the same reason as LegalDocuments above: `Accounts` alone says
// nothing about which console it belongs to.
use App\Livewire\Advanced\Citations;
// Aliased for the same reason as the two above: `Tickets` alone says nothing
// about which desk it belongs to, and `Account\Support` is a screen with the
// same word in its name one namespace over.
use App\Livewire\Advanced\Competitors;
use App\Livewire\Advanced\Credits;
use App\Livewire\Advanced\Defense;
use App\Livewire\Advanced\Home;
use App\Livewire\Advanced\Integrations;
use App\Livewire\Advanced\Posts;
use App\Livewire\Advanced\RankTracker;
use App\Livewire\Advanced\Reports;
use App\Livewire\Advanced\Segments;
use App\Livewire\Advanced\Settings;
use App\Livewire\Advanced\Visibility;
use App\Livewire\Advanced\Voice;
use App\Livewire\Setup\Done;
use App\Livewire\Setup\FindBusiness;
use App\Livewire\Setup\HowCustomersReach;
use App\Livewire\Setup\ReviewRules;
use App\Livewire\Setup\Welcome;
use App\Livewire\Support\Accounts as SupportAccounts;
use App\Livewire\Support\DataRequestQueue as SupportDataRequestQueue;
use App\Livewire\Support\Tickets as SupportTickets;
use App\Modules\X140\Ui\ProposedPagesView;
use App\Modules\X179\Ui\MatchScores;
use App\Modules\X179\Ui\ProspecttenantfacingTop3Preview;
use App\Services\ShortLinks\ShortLinks;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\SupportAccess;
use App\Support\MagicLinkRateLimits;
use App\Support\PasswordResetRateLimits;
use App\Support\RegistrationRateLimits;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\RoutePath;

/*
| The signed-out marketing surface (`29` §6.1). Public, no auth, no tenant —
| ResolveTenant runs on this path and resolves nothing, which is correct.
|
| `29` §6.1 lists eleven marketing pages; slice G2 builds the three that row 2
| needs, and the nav links only to what exists (decision 261).
*/
Route::get('/', [MarketingController::class, 'home'])->name('home');

Route::get('/signup', function () {
    if (! env('ALLOW_PUBLIC_SIGNUP', false) && ! config('app.allow_public_signup', false)) {
        abort(404);
    }

    return view('x-118::signup-page');
})->name('signup');

Route::get('/audit/{token}', [MarketingController::class, 'audit'])->name('audit.show');

/*
| The result partial as an HTML fragment, polled by the home page. Decision 258
| — one renderer for one result, rather than a JavaScript copy of the gauge that
| decision 236 went to some trouble to make structurally honest.
|
| Rate-limited on the same generous budget as the JSON poll: it costs a row read
| and the client asks roughly once a second for up to the 20-second audit budget.
*/
Route::get('/audit/{token}/result', [MarketingController::class, 'result'])
    ->middleware('throttle:public-audit-poll')
    ->name('audit.result');

/*
| Signup. Fortify already owns `POST /register` — `config/fortify.php` enables
| Features::registration() with `views => false`, so the endpoint exists and
| nothing renders a page in front of it. This is that page.
|
| Slice H adds the pre-fill: `/start?audit={token}` copies name, findings and
| categories into `wizard_progress.data` (BUILD-PLAN §2.5.2). It reads the query
| parameter that the home page's CTA already sends, so H changes what this does
| with the token rather than how anyone arrives here.
|
| ⚠️ NO LONGER A `Route::view`, AND THE REASON IS DECISION 691. This page
| promised "Seven days free" in two places while `billing.trial_days` had been
| **14** since decision 544 — the home page was corrected to read the key (518)
| and this one was missed, because a `Route::view` has nowhere to read a key
| from. The lint cannot catch it either: 511 records that the registry's numeric
| half is scoped to prices, and a trial length written in words is one of the
| three gaps it names out loud.
*/
Route::get('/start', [MarketingController::class, 'start'])->name('start');

/*
| CC-2's eight remaining `29` §6.1 pages, plus the six family demo doors and the
| hub shell CC-3 fills. Public, indexable, tenant-free, and none of them reaches
| tenant-owned data — the whole surface is registry rows and prose.
|
| ⚠️ UNTHROTTLED, LIKE THE OTHER STATIC PUBLIC PAGES. Each costs a view render and
| a handful of indexed reads against `platform_settings` and `plan_entitlements`;
| `/legal/{doc}`'s throttle exists because that route takes a parameter naming a
| row, and these do not.
|
| ⚠️ THE NAMES ARE THE SEAM AND ARE NOT FREE TO RENAME. `industries.index` is what
| CC-3 links its hundred landers back to, and `demo.family` is what a lander's one
| call to action resolves — a rename here is a rename in a lane that cannot see
| this file.
*/
Route::get('/pricing', [MarketingController::class, 'pricing'])->name('pricing');
Route::get('/features', [MarketingController::class, 'features'])->name('features');
Route::get('/compare', [MarketingController::class, 'compare'])->name('compare');
Route::get('/guarantee', [MarketingController::class, 'guarantee'])->name('guarantee');
Route::get('/faq', [MarketingController::class, 'faq'])->name('faq');
Route::get('/customers', [MarketingController::class, 'customers'])->name('customers');
Route::get('/affiliates', [MarketingController::class, 'affiliates'])->name('affiliates');
Route::get('/agencies', [MarketingController::class, 'agencies'])->name('agencies');

/*
| The six family doors. `DemoFamily` is a backed enum, so Laravel resolves the
| segment through `tryFrom()` and answers 404 for anything that is not one of the
| six — there is no seventh door and no `whereIn` list to keep in step with the
| enum (decision 5194).
*/
Route::get('/demo/{family}', [MarketingController::class, 'demo'])->name('demo.family');

/*
| The crawler's disclosure page — the `+URL` inside `RobotsPolicy::USER_AGENT`.
|
| ⚠️ THE PATH IS NOT FREE TO CHANGE. `GoAiEzBot/1.0 (+https://goaiez.com/bot)`
| already goes out on every robots.txt request and every page fetch slice D
| makes, so this route is the second half of a promise the crawler has been
| making since row 2. `BotPageTest` parses the URL back out of that constant and
| asserts it resolves here — moving one without the other fails the build rather
| than pointing every webmaster we fetch from at a 404.
|
| Unthrottled, like the other static public pages. It costs a view render, and
| the visitor is a person reading their access log.
*/
Route::get('/bot', BotController::class)->name('bot');

/*
| `/robots.txt`, served by the application rather than sat in `public/`.
|
| ⛔ IT WAS A STATIC FILE AND IT NEVER NAMED THE SITEMAP. Two lines, written
| 2026-07-31, untouched while CC-2 landed eleven marketing pages and CC-3
| landed a sitemap — because a file in `public/` belongs to no slice, so no
| slice updates it. Decision 5380.
|
| ⚠️ THE ROUTE IS OUTSIDE THE `RequireIndustryPages` GROUP ON PURPOSE. This
| address must always answer; what the flag decides is only whether the body
| carries a `Sitemap:` line, because the map it would name 404s while the flag
| is off. See the controller.
|
| Unthrottled, like `/bot` and the other static public pages: it is one string
| and its readers are crawlers we want to succeed.
*/
Route::get('/robots.txt', RobotsController::class)->name('robots');

/*
| `29` §6.1's /legal/{doc}. Built here, in a review-engine slice, because `24`
| §3.2 requires the consent disclosure to carry live links to Terms and Privacy
| and a dead link inside a TCPA disclosure is worse than a placeholder page.
| The documents themselves are counsel's, per prelaunch gate 1.
*/
Route::get('/legal/{doc}', LegalDocumentController::class)
    ->middleware('throttle:legal-document')
    ->name('legal.document');

/*
| The three URLs a 10DLC campaign registration carries: where a consumer opts in,
| the SMS programme terms, and the privacy policy. A carrier reviewer loads all
| three and checks the consent language on them (`24` §3.1; T137 R6's "10DLC
| registration ≠ recipient consent" is about what registration *proves*, not
| about what the submission needs).
|
| ⚠️ THE TWO LEGAL PAGES ARE `/legal/{doc}` AT A SECOND ADDRESS, NOT A COPY.
| Same controller, same `legal_documents` row, same publish-freeze trigger
| (417–420) — the short paths exist because a carrier form, an SMS footer and a
| printed QR card all carry a URL a person may retype, and `/legal/sms-terms` is
| four segments of typing where `/sms-terms` is one. `defaults()` supplies the
| `doc` the path does not, so a document can never be reached here by a name the
| enum does not carry. The canonical link element on the rendered page points
| back at `/legal/{doc}`, so the duplicate address costs nothing in search.
|
| ⚠️ AND THE ADDRESSES ARE NOT FREE TO CHANGE ONCE SUBMITTED. The same sentence
| `/f/{slug}` already carries above: a URL filed with a carrier under `24` §3.1
| is a promise to somebody who cannot be told it moved.
*/
Route::get('/sms-optin', SmsOptInController::class)
    ->middleware('throttle:legal-document')
    ->name('sms-optin');

Route::get('/sms-terms', LegalDocumentController::class)
    ->defaults('doc', LegalDocumentType::SmsTerms->value)
    ->middleware('throttle:legal-document')
    ->name('sms-terms');

Route::get('/privacy', LegalDocumentController::class)
    ->defaults('doc', LegalDocumentType::Privacy->value)
    ->middleware('throttle:legal-document')
    ->name('privacy');

/*
|--------------------------------------------------------------------------
| The one-click unsubscribe (T176 P21, RFC 8058)
|--------------------------------------------------------------------------
|
| One address, two verbs, and the split is the whole compliance property.
|
| ⛔ THE GET RENDERS A PAGE AND WRITES NOTHING. Every URL in an email is fetched
| by things that are not the recipient — mailbox malware scanners, corporate
| link rewriters, browser prefetchers — and decision 2920 records this
| application already paying that cost once, when a link checker's HEAD minted a
| full destination-click record on `/f/{slug}/to/{destination}`. There the GET
| could not be given up and a fetch classifier was the mitigation. Here it can.
|
| ⛔ THE POST TAKES NO SESSION, NO LOGIN AND NO CSRF TOKEN. RFC 8058 §3.1 has the
| mail client post `List-Unsubscribe=One-Click` unattended, from its own
| infrastructure; CAN-SPAM §7704(a)(3)(A) separately forbids requiring anything
| of the recipient beyond sending the reply. The exemption is named in
| `bootstrap/app.php` and what replaces the token is the sealed claim in the URL.
|
| ⚠️ NOT INSIDE THE `auth` GROUP AND NOT INSIDE ANY GROUP. `ResolveTenant` runs
| on the whole web group and fails closed for an unauthenticated request, which
| is correct and is why this controller establishes its own tenant from the token
| with `Tenancy::actingAs()` — `ShortLinkController`'s ordering exactly.
|
| The token is base64 in a URL-safe alphabet, so the constraint is the alphabet
| rather than a length: `UnsubscribeLinks` refuses anything over 4KB before it
| reaches the cipher.
|
*/
Route::get('/mail/unsubscribe/{token}', [UnsubscribeController::class, 'show'])
    ->where('token', '[A-Za-z0-9\-_~]+')
    ->middleware('throttle:mail-unsubscribe')
    ->name('mail.unsubscribe.show');

Route::post('/mail/unsubscribe/{token}', [UnsubscribeController::class, 'store'])
    ->where('token', '[A-Za-z0-9\-_~]+')
    ->middleware('throttle:mail-unsubscribe')
    ->name('mail.unsubscribe');

/*
| The hosted feedback page (`17` FPR-01) — the door every first-party review
| enters through. Public, no account, one stable address per location.
|
| `{slug}` rather than a token, and rather than route model binding. The slug is
| resolved by ResolveFeedbackPage, which is also what establishes the tenant —
| binding a tenant-scoped model before a tenant exists is the exact ordering
| problem bootstrap/app.php's priority list already solves for ResolveTenant, and
| a bound FeedbackPage would arrive with no is_published filter applied.
|
| `29`'s master route map puts hosted surfaces on {tenant}.goaiez.site. The
| microsite engine that owns that subdomain is a later row, so this lives on the
| primary domain for now — free to move until the first opt-in URL is submitted
| to a carrier under `24` 3.1, and not free afterwards.
*/
Route::middleware(ResolveFeedbackPage::class)->group(function (): void {
    // `feedback-view` guards against slug enumeration — the route is otherwise
    // unlimited, public, and tenant-establishing. `feedback-submit` is separate
    // and far tighter: the POST is where a farmed review would actually land.
    //
    // `feedback-view` also sits on `feedback.thanks`, not only `feedback.show`.
    // ResolveFeedbackPage runs on the whole group and 404s identically for an
    // unknown slug, so the thanks screen answers "does this slug exist?" exactly
    // as the form does — an unthrottled twin would just be the one an
    // enumeration script used instead.
    Route::get('/f/{slug}', [FeedbackPageController::class, 'show'])
        ->middleware('throttle:feedback-view')
        ->name('feedback.show');

    Route::post('/f/{slug}', [FeedbackPageController::class, 'store'])
        ->middleware('throttle:feedback-submit')
        ->name('feedback.store');

    Route::get('/f/{slug}/thanks', [FeedbackPageController::class, 'thanks'])
        ->middleware('throttle:feedback-view')
        ->name('feedback.thanks');

    /*
    | The destination hand-off (`17` FPR-04b, slice F). Records one
    | `destination_clicks` row and redirects to the platform.
    |
    | A GET THAT WRITES, WHICH IS DELIBERATE AND NOT FREE. It is the only shape
    | that satisfies BUILD-PLAN §2.6.3's "the picker renders with JavaScript
    | disabled": a plain anchor with target="_blank" is what a no-JS browser can
    | follow, and a POST form per button cannot be opened in a new tab by a
    | keyboard user the way a link can. The cost is that a prefetcher or a link
    | scanner can manufacture a click.
    |
    | ⛔ THAT COST WAS PAID UNTIL 2026-08-12, AND THE MITIGATION LISTED HERE WAS
    | NOT ONE (2920–2923). This comment used to bound it by "reachable only from
    | a noindex page, behind a session the visitor must have submitted the form
    | to hold, and rel=noopener plus no prefetch hints". A prefetch or prerender
    | is issued by the customer's OWN browser with the customer's OWN cookie, so
    | the session is no obstacle to the commonest case, and hints on an anchor do
    | not stop a browser or an in-page scanner deciding for itself. A HEAD from a
    | link checker minted the full record. FetchClassifier now runs on this route
    | — the same one the short-link redirector uses, T137 §3 rail 4 — and a
    | classified fetch is redirected exactly as a person's is while writing
    | nothing.
    |
    | `{destination}` binds to the ReviewDestination enum, so a value that is not
    | a case is a 404 before any code runs. ⚠️ `/to/yelp` IS NOT ONE OF THOSE AND
    | THIS COMMENT SAID IT WAS: the owner reversed decision 112 at 1160, Yelp is
    | a live case, and the URL binds. That is not a hole — 1161's
    | confirmed-listing rule is enforced in four places and the hand-off runs
    | `eligible()` — but the routing layer is not one of them, and a defence
    | claimed where none exists is what stops the next reader looking (2505).
    |
    | `feedback-view` rather than a limiter of its own: it is the same public
    | surface, and 30 requests a minute per (visitor, slug) is far above the two
    | or three taps a real customer makes.
    */
    Route::get('/f/{slug}/to/{destination}', [FeedbackPageController::class, 'destination'])
        ->middleware('throttle:feedback-view')
        ->name('feedback.destination');
});

/*
| The hosted review hub (`29` §7.6, Appendix A's `/r/{slug}`) — one public page
| per location, listing the reviews its owner approved for publication.
|
| ⛔ INSIDE ResolveFeedbackPage's GROUP, WHICH IS THE POINT AND NOT A SHORTCUT.
| That middleware is the only place in this application permitted to set a tenant
| on an unauthenticated request — its own docblock calls that "a
| privilege-escalation primitive by any other name" and confines it to "one
| narrow file with one input and no branches". A second tenant-establishing file
| for this route would have doubled that surface, and a second `public_read`
| table to resolve against would have made `review_hub_pages.title` and
| `intro_content` — tenant-authored prose — readable by every unauthenticated
| request in the system. So `/r/{slug}` takes the same slug as `/f/{slug}`: one
| public address per location, two verbs. `ReviewHubPages` explains the trade and
| what checks the stored copy of that slug.
|
| ⚠️ A SEPARATE GROUP RATHER THAN A SIXTH ROUTE IN THE ONE ABOVE. The block
| above is the feedback page and its picker, and its comments describe a form
| and a hand-off; this is a different surface that happens to share a resolver,
| and folding it in would make that block's header untrue about half its
| contents.
|
| `feedback-view` for the limiter, on that route's own reasoning: it is the same
| public surface, addressed by the same slug, and a miss counts against the
| visitor exactly as it does there — enumeration is the thing being guarded, and
| a page with an unthrottled twin is the one an enumeration script would use.
*/
Route::middleware(ResolveFeedbackPage::class)->group(function (): void {
    Route::get('/r/{slug}', ReviewHubController::class)
        ->middleware('throttle:feedback-view')
        ->name('review-hub.show');
});

/*
| Liveness probe. Deliberately shallow and dependency-free — it answers "is this
| process up", nothing more, so it stays honest during a database or Redis
| outage instead of reporting unhealthy and triggering a restart loop.
|
| Deep checks (database, Redis, queue depth, provider health) belong on a
| separate authenticated endpoint feeding the Ops health board — docs/29 §9.2
| and Part 8. Laravel's own probe stays at /up.
*/
/*
| The personalised MMS picture (T137 `SL-2`, lane `L3`).
|
| Public because a carrier fetches it, from an address nobody can predict, with
| no cookie and no session. `signed` is the whole authorisation and it runs at
| the routing layer, before the controller — an unsigned, edited or expired URL
| never reaches PHP that can read a row.
|
| ⚠️ THE TENANT IS A ROUTE PARAMETER AND THE SIGNATURE IS WHY THAT IS SAFE.
| `campaign_recipients` is tenant-owned and RLS-FORCEd, so the row cannot be read
| without a tenant, and there is nothing on an inbound carrier request to resolve
| one from — no host, no slug, no session. `feedback_pages` answered the same
| problem by being un-tenanted (decision 318); that is not open to a table
| holding send records, so the id travels inside a signature instead. A URL
| naming the wrong tenant finds nothing: RLS answers it, not a comparison
| somebody could delete.
|
| ⚠️ THROTTLED BECAUSE IT IS A PUBLIC ENDPOINT (§3.4). A signature makes a URL
| unguessable, not un-repeatable, and the limiter is what keeps one leaked URL
| from becoming a way to make this process read a file in a loop.
*/
Route::get('/m/{business}/{recipient}', CampaignMediaController::class)
    ->middleware(['signed', 'throttle:feedback-view'])
    ->whereNumber(['business', 'recipient'])
    ->name('campaign.media');

/*
| The AUTO-WITH-HOLD stop link — `BUILD-PLAN` §2.11.3 slice D.
|
| `29` §4.5: AUTO-WITH-HOLD *"proceeds unless they reply STOP within the
| window"*. This is the STOP. Public and signed for `campaign.media`'s reason
| exactly — the owner approves by text and never logs in, the notice arrives on
| a phone with no session, and there is nothing on the request to resolve a
| tenant from — so the tenant travels inside the signature and `growth_pages`,
| being FORCE row-level secured, answers a URL naming the wrong one with
| nothing.
|
| ⚠️ A GET THAT CHANGES STATE, DELIBERATELY. Mail clients and corporate
| scanners fetch links, and a fetch here means *"do not publish yet"* — the
| conservative direction on somebody else's website. That is the opposite of an
| unsubscribe link, whose scanner-fetch silently loses somebody their mail, and
| it is why this one is one step and those are two.
|
| ⚠️ THROTTLED, on the same reasoning as the route above: a signature makes a
| URL unguessable rather than un-repeatable.
*/
Route::get('/hold/{business}/{page}', HoldGrowthPageController::class)
    ->middleware(['signed', 'throttle:feedback-view'])
    ->whereNumber(['business', 'page'])
    ->name('content.growth-page.hold');

/*
| "Did we get that sorted?" — T546 §37.3(1), wave 38 lane C (10590–10609).
|
| `campaign.media` and `content.growth-page.hold`'s own reasoning, verbatim:
| the customer reaches this from a text or an email with no session, so
| `signed` is the whole authorisation and the tenant travels inside the URL
| because `triage_conversations` is FORCE row-level secured and there is
| nothing else on the request to resolve one from.
|
| ⚠️ TWO ROUTES, ONE URI, BOTH SIGNED — DELIBERATELY, UNLIKE THE GET-ONLY
| PRECEDENTS ABOVE. Laravel's signature covers the URL (path + query), never
| the HTTP verb, so the POST form on the rendered page targets this exact
| signed URL and validates against the identical signature the GET already
| proved. See FixThenAskCheckInController's own docblock for why the answer
| is a POST rather than a second state-changing GET: the conservative
| direction here is "nothing happened", and a scanner must never be able to
| record a confirmation nobody gave.
*/
Route::get('/checkin/{business}/{conversation}', [FixThenAskCheckInController::class, 'show'])
    ->middleware(['signed', 'throttle:feedback-view'])
    ->whereNumber(['business', 'conversation'])
    ->name('reviews.fix-then-ask.checkin.show');

Route::post('/checkin/{business}/{conversation}', [FixThenAskCheckInController::class, 'answer'])
    ->middleware(['signed', 'throttle:feedback-view'])
    ->whereNumber(['business', 'conversation'])
    ->name('reviews.fix-then-ask.checkin.answer');

/*
|--------------------------------------------------------------------------
| The industry engine — CC-3 (PIII-64A–E, PIII-72 §A)
|--------------------------------------------------------------------------
|
| One contiguous block, deliberately, and at the end of the public routes: CC-2
| is adding its own marketing routes to this file on another branch, and two
| lanes appending to one file merge cleanly only while each keeps its edits in
| one place (decision 960's lesson at the routes file rather than the
| architecture suite).
|
| ⚠️ THE WHOLE BLOCK IS GATED ON `features.industry_pages`, WHICH SEEDS FALSE.
| `RequireIndustryPages` answers 404 while it is off — not 503 and not a holding
| page, because a flag that is off means these addresses do not exist yet and
| that is what both a visitor and a crawler should be told.
|
| ⚠️ `industries.index` IS A CROSS-LANE SEAM. CC-2 §2.11 specifies the same route
| name for the hub shell it builds; this branch builds the whole hub because that
| shell is not here. Whichever lands second, the merge keeps ONE of the two and
| every `route('industries.index')` in either slice still resolves — which is the
| entire reason the name was agreed rather than the file.
|
| ⚠️ UNTHROTTLED, LIKE `/bot` AND THE HOME PAGE, AND UNLIKE `/f/{slug}`. The
| feedback page's limiter exists against slug enumeration; here the hub lists
| every slug on purpose, so there is nothing to enumerate. Each request is one
| indexed lookup, and the 301 path is a GIN containment query rather than a scan.
*/
Route::middleware(RequireIndustryPages::class)->group(function (): void {
    Route::get('/industries', [IndustryPageController::class, 'index'])->name('industries.index');

    Route::get('/industries/{slug}', [IndustryPageController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('industries.show');

    /*
    | The search surface. It flips with the pages rather than before them: a
    | sitemap is a request to be crawled, and this application has never served
    | one, so publishing the map and publishing the pages are one act.
    */
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
    Route::get('/sitemap-core.xml', [SitemapController::class, 'core'])->name('sitemap.core');
    Route::get('/sitemap-industries.xml', [SitemapController::class, 'industries'])
        ->name('sitemap.industries');
});

Route::get('/health', fn (): array => ['status' => 'ok'])->name('health');

/*
| ⛔ THE TWO TENANCY PROBES THAT SAT HERE ARE GONE, AND THEY WERE ON THE PUBLIC
| INTERNET ON EVERY DEPLOYMENT UNTIL 2026-08-25 (9760-9765). `/_tenancy/current`
| and `/_tenancy/locations` were closures whose own comment said "Not a feature",
| carrying no auth, no throttle and no environment guard — and production's
| `bootstrap/cache/routes-v7.php` held both names, so this was live rather than
| theoretical.
|
| ⛔ AND `/_tenancy/locations` ANSWERED A GUEST WITH A 500 AND A REPORTED STACK
| TRACE. `Location` is `BelongsToTenant`, `TenantNotResolved` is a bare
| RuntimeException with no status and no render(), and `bootstrap/app.php` adds
| no dontReport — so an unauthenticated GET was an unthrottled log write before
| it was anything else. Measured, not read: a guest request returned 500 in the
| test environment at a0db1c08.
|
| ⚠️ THE PROBES STILL EXIST AND STILL DRIVE REAL REQUESTS — see
| `tenancyProbeRoutes()` in `tests/Support/tenant_helpers.php`, which registers
| them per test. `ResolveTenant`'s two load-bearing properties are both about
| ordering and neither is visible when the middleware is called in isolation, so
| what moved is who registers the route, not whether the request is real.
| `TenancyProbeExposureTest` asserts the harness route's middleware stack is
| identical to a route declared in THIS file, so the observability cannot drift
| away quietly.
|
| ⛔ `TenantNotResolved` IS DELIBERATELY UNCHANGED. It reaching a request handler
| means this application queried tenant-owned data with no tenant, which is a
| defect and not a request to be refused politely — giving it a render() would be
| the safety net rewritten to approve of what it caught. What closed the vector
| is the door, not the alarm.
*/

/*
| Admin. Gated by one ability, defined in AdminAccess and asked for here and in
| every screen's mount() — never a role check inline, so FOUND-04 has exactly one
| place to wire the real check.
*/
Route::middleware(['auth', 'can:'.AdminAccess::GATE])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('automation-runs', AutomationRuns::class)
            ->name('automation-runs');

        // The Defaults Registry editor (`38` Part 2, CFG1). Takes no parameter,
        // so unlike LocationSettings and ReviewQueue it earns a place in
        // AdminNav.
        Route::get('settings', PlatformSettings::class)
            ->name('platform-settings');

        Route::get('industry-starting-points', IndustryStartingPoints::class)
            ->name('industry-starting-points');

        // The Credentials Manager (`38` Part 1, D-149). Sits behind the same one
        // gate as everything else here — see the component for why the
        // `support_agent` split `38` describes is not built alongside it.
        Route::get('credentials', CredentialsAdmin::class)
            ->name('credentials');

        // Autopilot settings for one location. Reached from `Extra locations`,
        // which is the only screen in the console that lists an account's
        // locations — the `admin.legal-documents` pattern, where a
        // parameterised screen is linked from an index rather than listed in a
        // nav that has no parameter to give it (9287).
        //
        // ⛔ THE CONSTRAINT IS NOT DECORATION AND ITS ABSENCE WAS A 500
        // (9286). Both components declare `mount(int $location)`, and Livewire
        // calls that through the container from a file with no
        // `strict_types`, so a path segment PHP cannot coerce raises an
        // uncaught `TypeError` — measured, `/admin/locations/abc/settings`
        // answered **500**, and so did `/admin/locations/null/reviews`. A
        // segment that is not a location id is a URL this application does not
        // have, so the honest answer is the router's 404 rather than an error
        // page.
        //
        // ⚠️ `whereNumber()` IS NOT ENOUGH, WHICH IS THE WHOLE REASON THIS IS A
        // LITERAL PATTERN. That helper is `[0-9]+`, which admits
        // `9999999999999999999999` — measured at **500** as well, because the
        // coercion overflows `int` rather than failing to parse. Eighteen
        // digits is the widest span that always fits (`PHP_INT_MAX` is
        // nineteen), so every accepted segment reaches `mount()` as an `int`.
        //
        // ⚠️ It refuses the segment, never the id. A location id belonging to
        // nobody is still a 200 carrying the account-number form, on purpose —
        // 9236's design reads nothing at all until an operator names an
        // account, and `AdminLocationScreensTest` pins that.
        Route::get('locations/{location}/settings', LocationSettings::class)
            ->where('location', '[0-9]{1,18}')
            ->name('location-settings');

        // The display queue (`17` FPR-05). Small on purpose — DASH-02's Reviews
        // Inbox is the real screen and is Sprint 9; this exists so
        // ReviewDisplay has a caller rather than being the sixth service in
        // this codebase written, documented, and never invoked.
        //
        // Reached from `Extra locations` and constrained for the reasons above,
        // in the same words: the component's `mount(int $location)` is what a
        // free-form segment reaches.
        Route::get('locations/{location}/reviews', ReviewQueue::class)
            ->where('location', '[0-9]{1,18}')
            ->name('review-queue');

        // The audit explorer (`28` §9.2's Platform section, §14.1). Two
        // routes because it answers two questions and only one of them has a
        // tenant to be scoped to — see StaffActivity's docblock, where the
        // reason it is not one screen with a filter is written down.
        Route::get('audit/account', AccountAudit::class)
            ->name('audit-account');

        Route::get('audit/staff', StaffActivity::class)
            ->name('audit-staff');

        // `28` §9.2's "internal users & roles", and §9.1's RBAC given a writer.
        // ⚠️ Behind the same `super_admin` gate as everything else in this
        // group, which means the screen that grants the first internal role
        // cannot be opened until one exists — `staff:grant` is the way in on a
        // fresh install, and the component's docblock says why that is a
        // console command rather than a wider gate here (748).
        Route::get('internal-users', InternalUsers::class)
            ->name('internal-users');

        // Drafting, review and publication for the platform's own legal
        // documents (`29` §6.1, `39`'s publish checklist). Not location-scoped
        // and not tenant-scoped: these are the terms every tenant is bound by.
        // The index, declared before the parameterised route so `/admin/legal`
        // is never matched as a document named "legal". It takes no parameter,
        // which is the whole reason the nav can link to it at all — see the
        // component, and `AdminNav`'s own note about why the drafting screen
        // could not be listed.
        Route::get('legal', LegalDocumentIndex::class)
            ->name('legal-index');

        Route::get('legal/{doc}', LegalDocumentsAdmin::class)
            ->name('legal-documents');

        // Health-information tenants and their Business Associate Agreements
        // (`29` §2 rule 24). No route parameter, on purpose: the screen looks a
        // business up by number and acts inside Tenancy::actingAs(), because
        // `businesses` admits a reader only as that business or as its owner —
        // see the component for why there is no list.
        Route::get('phi-tenants', PhiTenants::class)
            ->name('phi-tenants');

        // Extra locations, added by an operator (T176 P25). No route parameter,
        // for `PhiTenants`' reason and in the same words: the screen looks a
        // business up by number and acts inside Tenancy::actingAs().
        //
        // ⛔ ADMIN-ASSISTED IS THE TICKET'S ANSWER RATHER THAN A REDUCED VERSION
        // OF IT — "operator attaches the SKU per schedule; self-serve +
        // proration stay OUT". What a mid-cycle location add costs is open
        // question K and is the owner's (147–149), so the tenant screen at
        // `account.locations` quotes the price and sends them to a person.
        Route::get('tenant-locations', TenantLocations::class)
            ->name('tenant-locations');

        // What a business agreed to at signup, and the proof of it (T176 P22,
        // 3995). The read path for `terms_acceptances`, whose readers are a
        // person answering a carrier's question and a person answering a
        // subpoena — never this application's own logic.
        //
        // No route parameter, for `PhiTenants`' reason: `businesses` admits a
        // reader only as that business or as its owner, so the screen finds its
        // tenant by number rather than being handed one. That is also what lets
        // AdminNav link to it.
        Route::get('terms-acceptances', TermsAcceptancesAdmin::class)
            ->name('terms-acceptances');

        // What a business's own account holder agreed to be texted at, and
        // the proof of it (wave 39 lane A, 10660). The read path for
        // `owner_notification_consents`, which had a writer since wave 38
        // and no reader at all until this.
        //
        // ⛔ THIS COMMENT CALLED IT "THE ANSWER TO A CARRIER'S 'THIS NUMBER
        // NEVER AGREED TO BE TEXTED'" AND IT IS KEYED ON A BUSINESS NUMBER
        // (10882). It is the record for a business you can already name;
        // `admin.number-lookup` below is the index by number.
        //
        // No route parameter, for `TermsAcceptances`' and `PhiTenants`'
        // reason: `businesses` admits a reader only as that business or as
        // its owner, so the screen finds its tenant by number rather than
        // being handed one. That is also what lets AdminNav link to it.
        Route::get('owner-notify-consents', OwnerNotifyConsents::class)
            ->name('owner-notify-consents');

        // What we texted a business's own account holder and what they said
        // back (wave 41 lane E, 11110). The read path for `owner_notifications`
        // and `owner_replies`, four of whose columns had writers and no reader
        // anywhere in `app/` — 10843 named this screen and could not build it.
        //
        // ⛔ IT IS PART OF 10548's OWED SCREEN AND NOT THE WHOLE OF IT. That
        // row does not say whose eyes; this is platform staff's, and the
        // account holder still sees one activity-feed line and nothing else.
        //
        // No route parameter, for `OwnerNotifyConsents`' and `PhiTenants`'
        // reason: `businesses` admits a reader only as that business or as its
        // owner, so the screen finds its tenant by number rather than being
        // handed one. That is also what lets AdminNav link to it.
        Route::get('owner-channel-texts', OwnerChannelTexts::class)
            ->name('owner-channel-texts');

        // Who a phone number is, starting from the phone number (wave 40 lane
        // C, 10880). ⛔ THE ONE SCREEN IN THIS GROUP THAT IS NOT KEYED ON A
        // BUSINESS, AND THAT IS THE WHOLE POINT: a carrier complaint names a
        // number and nothing else, so every screen above it could only be
        // opened by somebody who already knew the answer.
        //
        // No route parameter, and here the reason is stronger than
        // `PhiTenants`': the subject is a stranger's phone number, and a URL is
        // the one place on this platform personal data is written into a server
        // log by default. It is typed, never routed.
        Route::get('number-lookup', NumberLookup::class)
            ->name('number-lookup');

        // T137 `SL-8`'s kill switches, given a door (2478, 2630). The
        // platform-wide halt and one tenant's sending pause on one screen,
        // because "sending is stopped" has three possible causes and an
        // operator who cannot see which one is looking at needs the third
        // rendered beside the two they can throw — see the component.
        //
        // No route parameter, for `PhiTenants`' reason: `businesses` admits a
        // reader only as that business or as its owner, so the screen finds its
        // tenant by number rather than being handed one. That is also what lets
        // AdminNav link to it.
        Route::get('sending', SendingControls::class)
            ->name('sending-controls');

        // T137 R3's visible sending limit, and the screen 4442 recorded as owed
        // (4600). Read-only and platform-wide: the ceiling belongs to one
        // sending account rather than to a tenant, so unlike the four screens
        // above there is no business to look up and nothing to act on.
        //
        // ⛔ NOT A PANEL ON `admin.sending-controls`. That screen is SMS only by
        // its own docblock — its rates are the 10DLC complaint trip's — and
        // putting email under the same heading would put two vendors' bounce
        // vocabularies behind one number.
        Route::get('mail-sending', MailSending::class)
            ->name('mail-sending');

        // The Google grants a deleted tenant left behind — decision 4888(a)
        // and (b). No route parameter: it lists every owed grant across the
        // platform rather than looking one business up, because the business
        // behind each row has already been destroyed and there is nothing
        // left to look up.
        Route::get('gbp-grant-revocations', GbpGrantRevocations::class)
            ->name('gbp-grant-revocations');

        // Every bell this platform has rung at its own operator — the reader
        // `operator_alerts` has never had. Every `OperatorAlerts::raise()` call
        // site in `app/` has been writing that table since 2026-08-15 and
        // nothing rendered a row, so the migration's own "what fired last
        // night, and what did it say" could only be answered with a database
        // client.
        //
        // No route parameter, and there could not be one: the table is
        // platform-scoped and carries no `business_id` at all, so there is no
        // account to look up. That is also what lets AdminNav link to it.
        //
        // ⛔ READ-ONLY. Nothing on the screen acknowledges, silences or deletes
        // an alert — R25, and because the row IS the de-duplication. See the
        // component.
        Route::get('operator-alerts', OperatorAlertBoard::class)
            ->name('operator-alerts');
    });

/*
| Support console (`28` §9.2). A separate gate from /admin rather than a wider
| one: every screen above is platform-global, and admitting `28` §9.1's five
| internal roles through that gate would let a `cs_readonly` edit a plan price.
| See SupportAccess.
*/
Route::middleware(['auth', 'can:'.SupportAccess::GATE])
    ->prefix('support')
    ->name('support.')
    ->group(function (): void {
        Route::get('accounts', SupportAccounts::class)->name('accounts');
        Route::get('data-requests', SupportDataRequestQueue::class)->name('data-requests');

        /*
        | T137 `SL-7`'s console queue — what accounts have asked us.
        |
        | On the support gate rather than `AdminAccess`, because answering a
        | customer is the support population's own job and `28` §9.1 puts none
        | of it behind the platform-global gate. Who may *reply* is a second and
        | narrower question, answered by `SupportTicketPolicy` rather than by
        | this line — see the component for why the two are different facts.
        |
        | No route parameter: a thread is opened in place, the way
        | `Support\Accounts` opens an account, so there is no id in a URL that
        | could name another tenant's ticket.
        */
        Route::get('tickets', SupportTickets::class)->name('tickets');
    });

/*
| Ending a support session.
|
| ⚠️ Outside the group above, and gated on `auth` alone. Inside an impersonated
| session the authenticated user *is* the owner, so the support gate would
| refuse the one request that gets the agent back out — the door would lock
| behind them. It is also the single route the view-only write refusal exempts
| (see Impersonating), for the same reason.
*/
Route::post('/impersonation/stop', ImpersonationController::class)
    ->middleware('auth')
    ->name('impersonation.stop');

/*
|--------------------------------------------------------------------------
| The owner's own account (`29` §11.2 row 5 — Pause)
|--------------------------------------------------------------------------
|
| ⚠️ The first authenticated owner-facing surface in this application that is
| not the wizard. `/setup` was the only one, which is why `SetupController`'s
| docblock says a finished owner has nowhere to go and why `Impersonation`
| redirects there for want of anywhere better.
|
| `auth` alone, and no tenant middleware of its own: `ResolveTenant` runs on the
| whole web group, so the component reads `Tenancy::idOrFail()` and fails closed
| for a signed-in user with no business rather than resolving to somebody else's.
|
| No route parameter, on decision 396's rule — and there is nothing here to
| parameterise: the tenant comes from the session, never from the URL.
*/
/*
| Home (`28` §3.3) — the owner's proof numbers, and the first Home this
| application has had. `auth` alone, for the reason `/account` gives below.
*/
Route::middleware('auth')
    ->get('/home', AccountHome::class)
    ->name('account.home');

Route::middleware('auth')
    ->get('/account/home', AccountHome::class);

/*
| What we have been doing (`28` §85's Activity entry, decision 6073) — the
| owner's own history of everything this platform did on their behalf.
|
| ⛔ THE FIRST AND ONLY DOOR ON `activity_feed`, WHICH HAS BEEN WRITE-ONLY SINCE
| STAGE 0. Thirty-one services and jobs file rows into it through
| `ActivityService`, `29` §2 rule 42 requires every automated action to reach it
| within sixty seconds, and nothing in `app/` rendered one — no screen, no
| route, no component (6073). The record this product keeps of what it did to
| somebody's business existed, grew daily, and could not be opened by the person
| it is kept for.
|
| ⚠️ A READ AND NOTHING ELSE. `activity_feed` is append-only at the model layer,
| so there is no action on this screen, no POST beside it, and nothing on the
| `SuspendedTenantStatus` exemption list — a suspended owner is sent to
| `/account/on-hold` before they reach any screen at all, and their answer is
| the one decision 820 keeps reachable: talk to us.
|
| `auth` alone, for the reason `/account` gives below: ResolveTenant runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business. No route parameter — the tenant
| never travels in a URL (decision 396's rule), and neither does a feed item id:
| there is no by-id read anywhere in this slice, so there is no door through
| which another tenant's row could be named.
*/
Route::middleware('auth')
    ->get('/account/activity', AccountActivity::class)
    ->name('account.activity');

Route::middleware('auth')
    ->get('/account/visibility', AccountVisibility::class)
    ->name('account.visibility');

Route::middleware('auth')
    ->get('/account', AccountSettings::class)
    ->name('account.settings');

Route::middleware('auth')
    ->get('/account/settings', AccountSettings::class);

/*
| The plan, and the cancellation this application promised and did not have
| (2980–2999).
|
| ⛔ `grep -rn "cancel" routes/*.php` RETURNED NOTHING BEFORE THIS PAIR. Every
| cancellation arrived inbound — a vendor notification, or Dunning exhausting
| its schedule — while `billing/authorize-net.blade.php` told every buyer "you
| can cancel any time". California's Automatic Renewal Law is about exactly that
| gap, and a promise on a checkout page the application cannot perform is worse
| than a missing feature.
|
| ⚠️ AND `/billing` HAD NO DOOR EITHER. It is reachable only from the
| post-registration redirect: no OwnerNav entry, no link from any /account
| screen, nothing in app/ that routes to it. So the cancellation lives in the
| account shell, where a returning owner actually is, and takes a nav entry —
| `Architecture/OwnerNavTest` fails the build on an owner screen with neither.
|
| ⚠️ A GET SCREEN AND A NAMED POST, NOT A LIVEWIRE ACTION, on 1900's reasoning:
| SuspendedTenantStatus exempts by *route name*, a Livewire button posts to
| `default-livewire.update` (1997), and `TenantSuspension::suspend()` does not
| stop the billing — so a suspended tenant is still being charged and both of
| these are on that middleware's exemption list.
*/
Route::middleware('auth')
    ->get('/account/plan', AccountPlan::class)
    ->name('account.plan');

Route::middleware('auth')
    ->post('/account/plan/cancel', CancelSubscriptionController::class)
    ->name('account.plan.cancel');

/*
| Locations — what the plan covers, and the only door in `app/` that makes one.
|
| ⛔ `TenantProvisioner` WAS THE ONLY THING IN THIS APPLICATION THAT COULD
| CREATE A LOCATION, so a tenant had exactly one for its whole life — while
| `SelectLocationController` further down exists precisely because "a tenant
| paying for a second location could not switch their review widget on by any
| route at all" (2969, 3060–3079). The picker was built for a population that
| could not exist. This screen and `BillingTermRequest`'s location count are the
| two halves of 2753's finding, and neither is safe without the other: a
| checkout that sells three locations with nothing able to make one bills a
| tenant for rows nobody can create.
|
| ⚠️ A SCREEN WITH A LIVEWIRE ACTION RATHER THAN A NAMED POST, which is the
| opposite of `account.plan.cancel` above and is argued rather than copied. That
| route is a named POST because `SuspendedTenantStatus` exempts by route name
| and a suspended tenant must still be able to cancel while the billing runs.
| **Adding a location is not on that exemption list and must not be**: a
| suspended tenant belongs at `/account/on-hold`, and creating new billable
| surface is the opposite of the case decision 820 keeps reachable.
*/
Route::middleware('auth')
    ->get('/account/locations', AccountLocations::class)
    ->name('account.locations');

Route::middleware('auth')
    ->get('/account/all-screens', AccountAllScreens::class)
    ->name('account.all-screens');

/*
| Credit — what a tenant has, and the only door in `app/` to the funder (3482).
|
| ⛔ `CreditTopUps` HAD NO CALLER ANYWHERE IN `app/`. The service, both gateway
| paths, the confirmation record and the settlement all existed and shipped
| green, and nothing a tenant could touch reached any of it — so every tenant's
| purchased balance across all three products was permanently zero and 3441's
| active-plan gate had nothing it could ever refuse. This screen is what 3482
| said was owed.
|
| ⚠️ IT IS A SCREEN AND NOT A POST PAIR, WHICH IS THE OPPOSITE OF
| `account.plan.cancel` ONE ROUTE UP — and deliberately. That route is a named
| POST because `SuspendedTenantStatus` exempts by route name and a suspended
| tenant is still being charged, so cancelling has to keep working while
| suspended. Buying credit does not: a tenant on hold has no business spending
| more money with us, and the middleware sending them to the on-hold page is the
| correct answer here rather than an exemption.
|
| `auth` alone, for the reason `/account` gives above: ResolveTenant runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business. No route parameter — the tenant
| never travels in a URL (decision 396's rule), and the two query flags the
| payment page hands back carry no amount, no reference and no identifier.
*/
Route::middleware('auth')
    ->get('/account/credit', AccountCredit::class)
    ->name('account.credit');

/*
| What we changed on your website — row 10 U1b, the owner's undo screen
| (`BUILD-PLAN` §2.11.3 slice J).
|
| ⛔ THE ONLY DOOR IN `app/` TO AN OWNER UNDO. `SiteChanges::revert()` had
| exactly one caller before this route — slice H's automatic rollback — so every
| revert this platform could perform was one it had decided on itself. `29` §2
| rule 32 makes a site change reversible; until this screen there was no way for
| the person whose website it is to reverse one.
|
| ⚠️ A SCREEN WITH LIVEWIRE ACTIONS RATHER THAN A NAMED POST, which is
| `/account/locations`' shape and not `/account/plan/cancel`'s. That route is a
| named POST because `SuspendedTenantStatus` exempts by route name and a
| suspended tenant must still be able to cancel while the billing runs.
| ⛔ THIS IS DELIBERATELY NOT ON THAT EXEMPTION LIST AND THE ARGUMENT CUTS THE
| OTHER WAY FROM `account.credit` ABOVE. Taking our own edit back off a
| suspended tenant's website is exactly the act 5813 says must never be gated on
| whether somebody is paying — but a suspended owner is sent to `/account/on-hold`
| before they reach any screen at all, and the answer for them is the same one
| decision 820 keeps reachable: talk to us. Wiring an exemption would put a
| button to write to a website on the one screen a suspended account can see.
| ⚠️ WHAT IS NOT GATED IS THE JOB (`UndoSiteChangeJob`), which keeps none of
| `AutopilotJob`'s pause and suspension gates for that exact reason.
|
| `auth` alone, for the reason `/account` gives above: ResolveTenant runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business. No route parameter — the tenant
| never travels in a URL (decision 396's rule), and neither does a change set
| id: the id the Undo acts on is `#[Locked]` component state, never a segment.
*/
Route::middleware('auth')
    ->get('/account/site-changes', AccountSiteChanges::class)
    ->name('account.site-changes');

/*
| Starting "Download my data" (`28` §3.7).
|
| ⚠️ A NAMED POST ROUTE RATHER THAN A LIVEWIRE ACTION, AND THE REASON IS THE
| SUSPENSION (1900). SuspendedTenantStatus exempts by *route name*, and a
| Livewire button posts to Livewire's own endpoint — `default-livewire.update`
| in this application, not `livewire.update` (1997) — so the exemption that
| looks right ("exempt account.settings") exempts a name this request never
| carries, and the only one that would work exempts every action on every
| screen. This route is exemptible at exactly the width of the promise §3.7
| makes, and it is what lets the on-hold page carry the control at all. See the
| controller's docblock.
*/
Route::middleware('auth')
    ->post('/account/exports', TenantExportRequestController::class)
    ->name('account.data-export.request');

/*
| "Download my data" (`28` §3.7). Five independent layers stand between this
| URL and a ZIP — see TenantExportDownloadController's own docblock for why all
| five are load-bearing rather than any one alone.
|
| `signed`, on top of `auth`: Laravel's own expiry check on the URL itself,
| minted by ExportBuilder::downloadUrl() from the row's own expires_at rather
| than a second `now()->addDays()` that could drift from it.
*/
Route::middleware(['auth', 'signed'])
    ->get('/account/exports/{export}/download', TenantExportDownloadController::class)
    ->whereNumber('export')
    ->name('account.data-export.download');

/*
| A picture a customer texted this business (T176 P10, skill 12).
|
| ⛔ NOT `signed`, AND THE CONTRAST WITH THE ROUTE ABOVE IS DELIBERATE. An export
| link is minted once and mailed; this is opened from a screen by somebody who is
| signed in to the business the picture belongs to. A signature would be a
| *weaker* control here — it keeps working after the person leaves the business,
| whereas `auth` plus the tenant resolution stops the moment their session does.
| The controller's docblock has all three layers.
*/
Route::middleware('auth')
    ->get('/account/messages/media/{media}', InboundMediaController::class)
    ->whereNumber('media')
    ->name('account.inbound-media.show');

/*
| `28` §9.5's status page for a suspended tenant.
|
| ⚠️ `/account/on-hold` RATHER THAN A PAGE RENDERED IN PLACE. A suspended owner
| refreshing a bookmarked `/account` must not be shown a hold notice at a URL
| claiming to be their settings — this has its own address, so "what is my
| account doing" has one answer they can read, copy and send to us.
|
| Exempt from `SuspendedTenantStatus` by name, or it is a redirect loop; and it
| redirects *away* for a tenant who is not suspended, so it cannot be linked at
| somebody as a way of telling them their account is fine.
*/
Route::middleware('auth')
    ->get('/account/on-hold', SuspendedAccountController::class)
    ->name('account.suspended');

/*
| The message log (`28` §3.4) — the reader `outreach_messages` never had (940).
| `auth` alone, for the reason `/account` gives above: ResolveTenant runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business.
*/
Route::middleware('auth')
    ->get('/account/messages', AccountMessages::class)
    ->name('account.messages');

/*
| The Inbox (R21, T176 P18) — the two-way half of the same subject.
|
| ⚠️ NOT THE SAME SCREEN AS `/account/messages` AND NOT A REPLACEMENT FOR IT.
| That one is the outbound log: everything we have sent on the owner's behalf,
| one chronological list, no threading and no reply. This is the conversation —
| what a customer texted the business, threaded, with a box to answer it and the
| two controls that decide whether the assistant is speaking. They read different
| tables and neither subsumes the other.
|
| `auth` alone, for the reason `/account` gives above: ResolveTenant runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business. No route parameter — a thread
| opens in place, so no conversation id ever travels in a URL (decision 396's
| rule, and one less thing to authorise).
*/
Route::middleware('auth')
    ->get('/account/inbox', AccountInbox::class)
    ->name('account.inbox');

/*
| Ask us something (T137 `SL-7`) — the tenant's half of the support desk.
|
| `auth` alone, for the reason `/account` gives above: `ResolveTenant` runs on
| the whole web group, so the component reads the tenant from the session and
| fails closed for a signed-in user with no business. No route parameter — a
| thread opens in place, so no ticket id ever travels in a URL (decision 396's
| rule, and one less thing to authorise).
*/
Route::middleware('auth')
    ->get('/account/support', AccountSupport::class)
    ->name('account.support');

/*
| Import (`29` §11.2 row 5, ahead of the First 7-Day path it feeds).
|
| `auth` alone for the same reason as `/account` above — `ResolveTenant` runs on
| the whole web group, so the component reads the tenant from the session and
| fails closed for a signed-in user with no business. No route parameter: the
| tenant is never in the URL (decision 396's rule).
*/
Route::middleware('auth')
    ->get('/account/customers/import', ImportCustomers::class)
    ->name('account.customers.import');

/*
| What the Business Brain answers from (lane L5 phase 1).
|
| ⛔ NOT A WIZARD STEP, AND THAT WAS A FINDING RATHER THAN A PREFERENCE — see
| `Account\Knowledge`'s own docblock and decision 2308. Inserting a step before
| `Done` would have required editing an applied migration whose integer→step
| `CASE` is a record of what those integers meant in rows written before it ran.
|
| `auth` alone, for the reason `/account` gives above: `ResolveTenant` runs on
| the whole web group, so the component reads the tenant from the session and
| fails closed for a signed-in user with no business. No route parameter — the
| tenant is never in the URL (decision 396's rule).
*/
Route::middleware('auth')
    ->get('/account/knowledge', AccountKnowledge::class)
    ->name('account.knowledge');

/*
| Your phone (T137 `R7`/`SL-9`) — the forwarding setup and the number it forwards
| to. The first writer `support_settings` has ever had.
|
| ⛔ NOT A WIZARD STEP, ON DECISION 2308's FINDING RATHER THAN A PREFERENCE —
| exactly as `/account/knowledge` above. Inserting a step before `Done` reddens
| `WizardStepTest`, and the only way back to green is editing an applied
| migration whose integer→step `CASE` records what those integers meant in rows
| written before it ran. A tenant who changes phone system also needs to revisit
| this, which a one-shot wizard step cannot offer. See decision 2910.
|
| `auth` alone, for the reason `/account` gives above: `ResolveTenant` runs on
| the whole web group, so the component reads the tenant from the session and
| fails closed for a signed-in user with no business. No route parameter — the
| tenant is never in the URL (decision 396's rule).
*/
Route::middleware('auth')
    ->get('/account/calls', AccountCalls::class)
    ->name('account.calls');

/*
| The message itself (T176 P2, decision 4519).
|
| ⛔ NOT `signed`, AND THE CONTRAST WITH `account.data-export.download` IS
| DELIBERATE — `account.inbound-media.show`'s argument, one channel over and
| about worse data. A signature keeps working after somebody leaves the business
| and survives being forwarded; `auth` plus the tenant resolution stops the
| moment their session does. It is also why the owner's notification carries no
| link at all: the mail says the recording is on their calls page, and this is
| that page's reader rather than a second way in.
|
| ⚠️ THE ROUTE EXISTS BECAUSE THE MAIL ALREADY PROMISED IT. Until this, every
| recording fetched was write-only personal data in object storage with nothing
| that could play it.
*/
Route::middleware('auth')
    ->get('/account/calls/voicemail/{voicemail}', VoicemailRecordingController::class)
    ->whereNumber('voicemail')
    ->name('account.voicemail.recording');

/*
| What your assistant can send (T176 §2.4, P6) — the booking URL, the payment
| URL with its call-out fee, and the documents a business shares.
|
| ⛔ NOT A WIZARD STEP, ON DECISION 2308's FINDING — exactly as `/account/knowledge`
| and `/account/calls` above. §2.4 does describe this as an SL-9 wizard step, and
| inserting one before `Done` would mean editing an applied migration whose
| integer→step `CASE` records what those integers meant in rows written before it
| ran. A business also changes scheduler, payment provider and price sheet over
| the years, which a one-shot step cannot offer.
|
| `auth` alone, for the reason `/account` gives above: `ResolveTenant` runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business. No route parameter — the tenant is
| never in the URL (decision 396's rule).
*/
Route::middleware('auth')
    ->get('/account/assistant-links', AccountAssistantLinks::class)
    ->name('account.assistant-links');

/*
| What your assistant can quote (T176 §2.4, P5) — the price list with the
| disclaimer line R13 requires with every quote, the doc-ingest path and its
| review-before-live gate, and the urgent terms with the emergency line.
|
| ⛔ NOT A WIZARD STEP, ON DECISION 2308's FINDING — the same argument
| `/account/assistant-links` above makes, and stronger here: a business changes
| its prices every year, which a one-shot step cannot offer.
|
| `auth` alone, for the reason `/account` gives above: `ResolveTenant` runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business. No route parameter — the tenant is
| never in the URL (decision 396's rule).
*/
Route::middleware('auth')
    ->get('/account/assistant-answers', AccountAssistantAnswers::class)
    ->name('account.assistant-answers');

/*
| The tenant CRM (`34` §1.1 and §1.2) — the reader `customers` never had.
|
| `auth` alone for the reason `/account` gives above: `ResolveTenant` runs on the
| whole web group, so the components read the tenant from the session and fail
| closed for a signed-in user with no business.
|
| ⚠️ `whereNumber` IS LOAD-BEARING, NOT TIDINESS. `/account/customers/import`
| above and `/account/customers/{customer}` here share a prefix, so without a
| numeric constraint the profile route would match the literal `import` — and
| which one wins would depend on registration order, which is exactly the kind of
| coupling that survives every test until somebody reorders this file. Doc `44`
| §9 adds more verbs under this prefix; they all sit above the parameter and are
| all protected by this constraint rather than by their position.
|
| ⚠️ The parameter is an id and NOT a model, deliberately: `CustomerProfile`
| resolves it through `CustomerDirectory` so that the tenant refusal lives in
| code the component owns and stays falsifiable in a component test, where no
| middleware and no route binding run at all (decision 809).
*/
/*
| Follow-ups (`44` §2) — the reader and writer `crm_tasks` never had (1221).
| `auth` alone for the reason `/account` gives above. Lives under More in the
| owner's nav, which badges that tab with the due-today count.
*/
Route::middleware('auth')
    ->get('/account/follow-ups', AccountFollowUps::class)
    ->name('account.follow-ups');

/*
| Reply drafts (`17` GBP-05) — the approval queue `replies` never had.
| Under More, badged with the suggested count. Posting to Google handoffs until
| GbpClient gains a confirmed create-reply endpoint.
*/
Route::middleware('auth')
    ->get('/account/replies', AccountReplyQueue::class)
    ->name('account.replies');

Route::middleware('auth')
    ->get('/account/facebook-reviews', AccountFacebookReviews::class)
    ->name('account.facebook-reviews');

/*
| The recovery queue (`17` TRIAGE-03/04, decision 2689) — where a below-threshold
| customer's conversation is actually worked. Decision 114's surviving half had a
| table and a router that opened rows on it, and no screen at all; `TriageStatus`
| could only ever hold `Open`, so the owner's "customers won back" was
| structurally zero.
|
| `auth` alone for the reason `/account` gives above: `ResolveTenant` runs on the
| whole web group, so the component reads the tenant from the session and fails
| closed for a signed-in user with no business. No route parameter — the
| conversation id travels in the Livewire action, never in the URL (396's rule).
*/
Route::middleware('auth')
    ->get('/account/win-back', AccountWinBack::class)
    ->name('account.win-back');

Route::middleware('auth')
    ->get('/account/customers', AccountCustomers::class)
    ->name('account.customers');

Route::middleware('auth')
    ->get('/account/customers/{customer}', AccountCustomerProfile::class)
    ->whereNumber('customer')
    ->name('account.customers.show');

/*
|--------------------------------------------------------------------------
| Advanced Dashboard Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', EnsureAdvancedDashboard::class])
    ->prefix('advanced')
    ->name('advanced.')
    ->group(function () {
        Route::get('/', Home::class)->name('home');
        Route::get('/citations', Citations::class)->name('citations');
        Route::get('/visibility', Visibility::class)->name('visibility');
        Route::get('/competitors', Competitors::class)->name('competitors');
        Route::get('/defense', Defense::class)->name('defense');
        Route::get('/changes', Changes::class)->name('changes');
        Route::get('/broadcasts', Broadcasts::class)->name('broadcasts');
        Route::get('/broadcasts/compose', BroadcastComposer::class)->name('broadcasts.compose');
        Route::get('/broadcasts/new', BroadcastComposer::class)->name('broadcasts.new');
        Route::get('/segments', Segments::class)->name('segments');
        Route::get('/reports', Reports::class)->name('reports');
        Route::get('/credits', Credits::class)->name('credits');
        Route::get('/settings', Settings::class)->name('settings');
        Route::get('/rank-tracker', RankTracker::class)->name('rank-tracker');
        Route::get('/integrations', Integrations::class)->name('integrations');
        Route::get('/posts', Posts::class)->name('posts');
        Route::get('/voice', Voice::class)->name('voice');
        Route::get('/website-builder', fn () => redirect()->route('x-103.pages'))->name('website-builder');
    });

/*
|--------------------------------------------------------------------------
| Connecting Google Business (row 3 slice H)
|--------------------------------------------------------------------------
|
| `auth` alone on the screen, for the reason `/account` gives above:
| `ResolveTenant` runs on the whole web group, so a signed-in user with no
| business fails closed rather than seeing somebody else's locations.
|
| ⚠️ THE CALLBACK IS SIGNED, AND THE FOUR EXCLUDED PARAMETERS ARE WHY IT HAS TO
| BE. Zernio appends `connected`, `profileId`, `accountId` and `username` to
| whatever redirect URL we hand it and carries no state of ours through the
| flow — so those four cannot be part of the signature, and `accountId` is the
| single value deciding whose Google reviews this application reads. Without a
| signature, any signed-in owner could type another tenant's account id into the
| address bar. `GbpConnectController` names the three layers that stand between
| that and a row; this is the first of them.
|
| ⚠️ The route parameter is the location id and NOT a bound model, on decision
| 396's rule — Laravel splices container-resolved arguments into the positional
| list, and this action takes two of them before the parameter.
*/
Route::middleware('auth')
    ->get('/account/connections', AccountConnections::class)
    ->name('account.connections');

Route::middleware(GbpConnectController::middleware())
    ->get('/account/connections/google/callback/{location}', GbpConnectController::class)
    ->whereNumber('location')
    ->name('gbp.connect.callback');

Route::middleware(WhatsappConnectController::middleware())
    ->get('/account/connections/whatsapp/callback', WhatsappConnectController::class)
    ->name('whatsapp.connect.callback');

Route::middleware(SocialConnectController::middleware())
    ->get('/account/connections/social/{platform}/callback', SocialConnectController::class)
    ->whereIn('platform', ['facebook', 'instagram'])
    ->name('social.connect.callback');

/*
|--------------------------------------------------------------------------
| Connecting Google Search Console (`28` §5.3, row 15 slice 1)
|--------------------------------------------------------------------------
|
| ⚠️ ITS OWN CONTROLLER AND ITS OWN ROUTES, NOT A THIRD PROVIDER ON
| /auth/{provider}/redirect. Decision 1082: that route's LOGIN_PROVIDERS
| allowlist is what stops somebody signing in with a connect-only grant, and
| adding `gsc` to it would make a Search Console reporting scope into a working
| authentication method. `OauthLoginController`'s own docblock anticipates this
| — "the set of providers you may sign in with is smaller than the set the
| platform connects to".
|
| `auth` alone, for the reason `/account` gives above: `ResolveTenant` runs on
| the whole web group, so the controller reads the tenant from the session and
| fails closed for a signed-in user with no business. No route parameter, on
| decision 396's rule — the tenant is never in the URL.
|
| ⚠️ The callback path must ALSO be registered as an authorized redirect URI in
| the Google Cloud console, beside the sign-in one. They share an OAuth client
| and differ only by redirect; an unregistered one fails with
| `redirect_uri_mismatch`, whose message names neither this file nor that
| setting.
*/
Route::middleware('auth')->group(function (): void {
    Route::get('/account/search-console/connect', [SearchConsoleConnectController::class, 'redirect'])
        ->name('gsc.connect.redirect');

    Route::get('/account/search-console/callback', [SearchConsoleConnectController::class, 'callback'])
        ->name('gsc.connect.callback');
});

/*
|--------------------------------------------------------------------------
| Onboarding wizard (`29` §7.2, COMP-02)
|--------------------------------------------------------------------------
|
| The first authenticated *owner*-facing surface in this application. Until it
| existed, config/fortify.php pointed `home` at the marketing page because there
| was nothing else to point it at, and /admin is gated on isPlatformStaff().
|
| One explicit route per step rather than /setup/{step} with a bound enum.
| Decision 396: Laravel splices container-resolved arguments into the positional
| parameter list, so a route parameter that a component does not declare lands
| in the wrong slot and dies with a TypeError that reads exactly like implicit
| enum binding having failed. A route with no parameter cannot hit it.
|
| Five of `29` §7.2's fourteen steps. The rest depend on rows that do not exist
| — Connect Google is row 3 slice H and the GBP API approval is pending. The
| spine is what stops the next row inventing a second one.
|
*/
/*
|--------------------------------------------------------------------------
| Billing (row 22 slice B)
|--------------------------------------------------------------------------
|
| Two authenticated screens and one public endpoint, and the split matters:
| Checkout is something a signed-in owner starts, and a webhook arrives from a
| machine with no session, no tenant and no cookie.
|
| ⚠️ `POST /webhooks/stripe` IS EXEMPT FROM CSRF, WHICH IS BOTH NECESSARY AND
| SAFE. Stripe cannot hold a token, and Laravel would reject every delivery
| without the exemption (`bootstrap/app.php` names the path). What replaces the
| token is stronger than one: every request is HMAC-signed with a shared secret
| and checked inside a five-minute window, so a forged post is refused whether or
| not it carries a session cookie — which a CSRF token cannot claim.
|
| ⚠️ THIS IS *NOT* CASHIER'S `POST stripe/webhook`. That route registers itself
| unless `Cashier::ignoreRoutes()` is called, and nothing had ever called it —
| see AppServiceProvider and decision 683.
|
*/
Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

/*
| ⚠️ `POST /webhooks/authorize-net` — the second gateway (2056, T137 R2/SL-11).
|
| Also CSRF-exempt, and what replaces the token is HMAC-SHA512 over the raw body
| in an `X-ANET-Signature` header. **One thing the Stripe comment above claims is
| NOT true here**: Authorize.Net's signature covers the body alone and carries no
| timestamp, so there is no five-minute window and a captured notification
| replays forever. The unique `notification_id` claim row is the replay defence
| on this endpoint — see `AuthorizeNetWebhooks`.
*/
Route::post('/webhooks/authorize-net', AuthorizeNetWebhookController::class)
    ->name('webhooks.authorize-net');

Route::middleware('auth')->prefix('billing')->name('billing.')->group(function (): void {
    Route::get('/', [BillingController::class, 'index'])->name('index');

    // A GET that opens a Checkout Session — see the controller for why, and for
    // what stops a refresh producing a second subscription.
    Route::get('checkout', [BillingController::class, 'checkout'])->name('checkout');

    /*
    | The Authorize.Net card form and its exchange.
    |
    | ⚠️ A GET AND A POST, WHERE STRIPE NEEDS ONLY A GET, AND THE DIFFERENCE IS
    | THE VENDOR'S. Hosted Checkout takes the browser away and brings it back;
    | Accept.js keeps the form here, so there is a page to render and a nonce to
    | receive. The POST is a genuine mutation and carries a CSRF token like any
    | other form — it is not a webhook and is not exempt.
    */
    Route::get('card', [AuthorizeNetCheckoutController::class, 'create'])->name('card');
    Route::post('card', [AuthorizeNetCheckoutController::class, 'store'])->name('card.store');
});

Route::middleware('auth')->prefix('setup')->name('setup.')->group(function (): void {
    Route::get('/', SetupController::class)->name('index');

    Route::get('welcome', Welcome::class)->name('welcome');
    Route::get('find-business', FindBusiness::class)->name('find-business');
    Route::get('how-customers-reach', HowCustomersReach::class)->name('how-customers-reach');
    Route::get('review-rules', ReviewRules::class)->name('review-rules');
    Route::get('done', Done::class)->name('done');
});

/*
|--------------------------------------------------------------------------
| Authentication (FOUND-04)
|--------------------------------------------------------------------------
|
| Fortify registers login, logout, registration, password reset, two-factor and
| every passkey endpoint on its own. Only what Fortify does not cover lives here:
| the two views the framework needs by name, magic links, and SSO.
|
| `fortify.views` is false, which drops Fortify's seven scaffolding screens but
| none of its POST endpoints. Two GET routes still have to exist, and both are
| required by the framework rather than by us:
|
|   login           Illuminate\Auth\Middleware\Authenticate redirects here by
|                   name when an unauthenticated request hits a guarded route
|   password.reset  Illuminate\Auth\Notifications\ResetPassword builds its URL
|                   from this route name
|
| The screens themselves are placeholders for the design system in `22`. What is
| NOT placeholder is the throttling and the naming below.
|
*/

/*
| ⛔ THE VENDOR-REGISTERED DOORS THAT SHIPPED WITH NO THROTTLE AT ALL (9505,
| 9620, 9623, 9634). `POST /forgot-password`, `POST /reset-password` and
| `POST /register` are Fortify's routes, and Fortify reads a limiter for
| `login`, `two-factor`, `passkeys` and `verification` and for **none of these
| three** — so all three carried `['web', 'guest:web']` and nothing else, while
| `POST /login` in the same vendor route file carried `throttle:login`. Measured
| at the route with `gatherMiddleware()`, not read off the config. The fourth
| entry below is `laravel/passkeys`' own asymmetry rather than Fortify's.
|
| ⚠️ THE MIDDLEWARE IS APPENDED TO FORTIFY'S OWN ROUTE RATHER THAN THE ROUTE
| BEING RE-DECLARED HERE, AND THE DIFFERENCE IS NOT STYLE.
|
|   a re-declaration owns Fortify's URI, its controller, its `guest:` guard and
|   — the one that decides it — **its feature flag**. `Features::resetPasswords()`
|   is the owner's to turn off (9505 says so explicitly), and a copy of those two
|   routes living here would go on serving them after it was turned off. A
|   security feature switched off in config and still answering is worse than
|   what this fixes.
|
|   appending cannot resurrect anything: the loop walks the routes that EXIST,
|   so a route the package did not register is a name that matches nothing and
|   the loop does nothing. It also cannot drift — Fortify keeps owning the URI,
|   the controller and the guard, and if it ever adds middleware of its own we
|   inherit it instead of dropping it. ⚠️ The failure mode it DOES have is a
|   silent no-op if a package renames a route, which is why
|   `ObservabilityTest` §11 asserts these four by name at the route.
|
| ⛔ IT IS DONE HERE RATHER THAN IN A `booted()` CALLBACK IN
| `FortifyServiceProvider`, AND THAT ORDERING IS A PRODUCTION-ONLY TRAP THAT WAS
| MEASURED RATHER THAN REASONED ABOUT (9624). `RouteServiceProvider::register()`
| loads the CACHED routes from inside a nested `booted` callback, so a callback
| registered during a provider's `boot()` is queued ahead of it. Counted from a
| probe registered exactly that way, in this worktree, on 2026-08-25:
|
|   with `route:cache`     **9** routes visible to the callback
|   without `route:cache`  **191**
|
| `composer deploy` runs `route:cache` and no test does — so that placement
| attaches every throttle in the suite and none in production, silently, with
| the build green. This file executes at cache time and at boot time alike,
| which is the only placement that is true in both.
|
| ⛔ AND NONE OF THE THREE FIGURES CLOSES AN ORACLE. They bound how fast a
| stranger may ask. What the doors ANSWER is closed for the two password-reset
| routes by the bindings in `FortifyServiceProvider`, and is still open on
| `/register` by a deliberate product trade (9506, 9628).
*/
$fortifyThrottles = [
    // A stranger causes mail to be sent to an address they chose — the magic
    // link's own reason, one screen over: "without a limit it is a mail cannon
    // pointed at anyone whose address is guessed."
    'password.email' => PasswordResetRateLimits::REQUEST_LIMITER,

    // A stranger causes a bcrypt verify, before the password rules are even
    // reached. See PasswordResetRateLimits: guessing the token was never
    // possible, and the work is what is unbounded.
    'password.update' => PasswordResetRateLimits::ATTEMPT_LIMITER,

    // ⚠️ A SECOND CEILING ON A DOOR THAT ALREADY HAD ONE, AND IT IS NOT A
    // DUPLICATE. `RegistrationRateLimits::enforce()` bounds ACCOUNTS CREATED
    // and runs inside `CreateNewUser` after validation, so a duplicate-address
    // probe throws on `Rule::unique` and never reaches it. This bounds
    // REQUESTS, and it is deliberately ten times the inner figure so that a
    // genuine registration still meets the inner one first (398).
    'register.store' => RegistrationRateLimits::REQUEST_LIMITER,

    // ⚠️ A FOURTH, AND IT IS THE VENDOR'S OWN ASYMMETRY RATHER THAN A DOOR THIS
    // APPLICATION LEFT OPEN. `laravel/passkeys` builds three middleware stacks
    // and puts `throttle:passkeys` on two of them; `passkey.destroy` takes
    // `$passkeyMiddleware` WITHOUT the throttle, so six of the seven passkey
    // routes are limited and the seventh is not. It is authenticated and behind
    // password confirmation, so the exposure is small — which is why it is a
    // tidy-up rather than a finding — but a route deleting a credential is a
    // strange one to leave as the exception, and the same bucket its six
    // siblings share is the obvious answer.
    'passkey.destroy' => 'passkeys',
];

/*
| ⛔ A SCAN AND NOT `getRoutes()->getByName()`, AND THE FIRST DRAFT WAS THE
| SECOND — IT ATTACHED NOTHING AND SAID NOTHING (9625). `RouteCollection`'s
| name index is populated by `addLookups()` at the moment a route is ADDED, and
| Fortify — like every route in this file — calls `->name()` on the Route object
| **after** `Route::post()` has returned it. So the index does not know any of
| those names until `RouteServiceProvider` calls `refreshNameLookups()`, which
| happens in a `booted` callback registered after this file has already run.
| `getByName('password.email')` therefore answered null here, `?->` swallowed it,
| and `gatherMiddleware()` at the route still read `web,guest:web` with the
| build green. Measured; the fix is to ask the routes rather than the index.
*/
foreach (Route::getRoutes()->getRoutes() as $fortifyRoute) {
    $limiter = $fortifyThrottles[$fortifyRoute->getName()] ?? null;

    if ($limiter !== null) {
        $fortifyRoute->middleware('throttle:'.$limiter);
    }
}

Route::middleware('guest')->group(function (): void {
    /*
    | ⚠️ NO LONGER A `Route::view`, AND THE REASON IS DECISION 691's, ONE PAGE
    | OVER (T176 P22). This screen carries the **second signup door** — a first
    | "Continue with Google" creates an account and a tenant — so it renders the
    | terms notice beside those buttons and links the exact published documents,
    | and a view route can resolve neither.
    */
    Route::get('/login', LoginPageController::class)->name('login');
    Route::get('/register', fn () => redirect()->route('start'))->name('register');

    Route::view('/reset-password/{token}', 'auth.reset-password')->name('password.reset');

    /*
    | ⛔ A FOURTH ROUTE THAT EXISTS BECAUSE FORTIFY PUTS A GET INSIDE
    | `if ($enableViews)` AND ITS POST OUTSIDE — and this one was neither a
    | lockout nor a 404 but something in between (9628b, 9880).
    |
    | `POST /forgot-password` has been live for the life of this application:
    | throttled at five an hour on a `HashedIp` (9620), answering one sentence
    | for a known and an unknown address (9621), metered through
    | `PlatformMailer` (9600). What it never had was a page. So the endpoint
    | that mails a sign-in credential was reachable by every stranger with
    | `curl` and by no customer with a browser, and `login.blade.php` did not
    | link to it either — the wrong way round, which is 9628's own argument for
    | building the screen rather than removing the endpoint.
    |
    | ⚠️ WHAT A BROWSER ACTUALLY MET WAS `405 Method Not Allowed`, WITH
    | `allow: POST`, AND NOT THE 404 EVERY ARTEFACT ASSUMED. Measured against a
    | running server on 2026-08-26, before this route existed. The URI resolved;
    | only the method did not. It changes nothing about what is owed, and it is
    | written down because "the customer gets a 404" was the sentence everybody
    | had — including the brief this route was built from.
    |
    | ⛔ `fortify.views` STAYS FALSE. Flipping it registers Fortify's own views
    | for EVERY auth screen at once and displaces the hand-rolled ones this
    | application ships — the login page's four doors, the 2FA challenge, the
    | password confirmation. Registering the one GET here is the same pattern
    | `login` and `password.reset` above already use, and it is the placement
    | 9624 requires: `composer deploy` runs `route:cache`, and a routes file
    | executes at cache time and at boot time alike where a `booted()` callback
    | does not.
    |
    | ⚠️ NO THROTTLE, AND IT IS ARGUED RATHER THAN OVERLOOKED. This GET renders
    | a static view: no database read, no mail, no caller-controlled parameter,
    | nothing spent. What it submits to carries
    | `throttle:password-reset-request` — five an hour, keyed on `HashedIp` —
    | exactly as `login` and `password.reset` are argued in
    | `ObservabilityTest` §11's census, where this route is now named.
    |
    | `RoutePath::for()`, not a literal: Fortify builds the POST half through
    | it, so a `fortify.paths` entry would move one and not the other — a form
    | posting to nothing.
    */
    Route::view(RoutePath::for('password.request', '/forgot-password'), 'auth.forgot-password')
        ->name('password.request');

    /*
    | The second step of a sign-in, for anyone holding a second factor.
    |
    | ⚠️ A THIRD ROUTE THAT EXISTS BECAUSE THE FRAMEWORK REDIRECTS TO IT BY NAME,
    | and its absence was a lockout rather than a 404 (decision 660). Fortify
    | registers this GET inside `if ($enableViews)` and its POST outside, so with
    | `fortify.views` false the name resolved nowhere — while
    | RedirectIfTwoFactorAuthenticatable ends every password login by a user with
    | a confirmed factor at `route('two-factor.login')`. Enabling 2FA locked the
    | account out by succeeding at the password step.
    |
    | RoutePath::for(), not a literal: Fortify builds the POST half through it,
    | so a `fortify.paths` entry would move one and not the other — a challenge
    | screen whose form posts to nothing.
    */
    Route::view(RoutePath::for('two-factor.login', '/two-factor-challenge'), 'auth.two-factor-challenge')
        ->name('two-factor.login');

    /*
    | Magic link — the passwordless route that is not passkeys.
    |
    | Throttled hard on both legs, for different reasons. The request leg is an
    | unauthenticated endpoint that causes an email to be sent to an address the
    | caller chose: without a limit it is a mail cannon pointed at anyone whose
    | address is guessed. The consume leg is a bearer-credential in a URL, so the
    | limit is what makes brute-forcing 64 characters pointless rather than
    | merely impractical.
    |
    | ⛔ THOSE TWO SENTENCES WERE RIGHT AND THE THROTTLES UNDER THEM SAID
    | `throttle:5,1` AND `throttle:10,1` UNTIL 2026-08-25 (9720). They were the
    | only two inline throttles this application declares — the third is
    | `POST `livewire.upload-file``, which is the vendor's own default and is
    | exempted by name in ObservabilityTest §11 — and three things followed from
    | that which no artefact here recorded:
    |
    |   the request leg was **300 an hour** where its sibling door,
    |   `POST /forgot-password`, is five — sixty times looser on the two
    |   endpoints that both mail a sign-in credential to a caller-chosen address;
    |
    |   an inline throttle cannot be keyed. `ThrottleRequests` falls through to
    |   `resolveRequestSignature()`, which is `sha1($domain.'|'.$request->ip())`
    |   — the raw address under an unsalted digest, which 9620 rejected in
    |   writing and `ObservabilityTest` §11's own failure message forbids;
    |
    |   and the two legs SHARED ONE BUCKET, because an inline throttle's cache
    |   key is the request signature with no prefix. Measured rather than read:
    |   five GETs at the consume leg left the next POST to the request leg
    |   answering 429. The consume leg was a denial-of-service lever on the
    |   request leg, reachable by the mail scanners MagicLinkService names.
    |
    | ✅ Both figures now live in `App\Support\MagicLinkRateLimits`, where a
    | number has to be argued and where §11's first two arms can see it. The
    | third limit — the per-recipient cooldown the reset door has always had
    | through `passwords.users.throttle` — is enforced inside
    | `MagicLinkService::request()`, because a per-network figure cannot bound a
    | flood at one mailbox from many addresses.
    |
    | Named `magic-link.consume` because MagicLinkService builds the emailed URL
    | from that name — route() everywhere, never a hand-built string.
    */
    Route::post('/auth/magic-link', [MagicLinkController::class, 'store'])
        ->middleware('throttle:'.MagicLinkRateLimits::REQUEST_LIMITER)
        ->name('magic-link.request');

    Route::get('/auth/magic-link/{token}', [MagicLinkController::class, 'show'])
        ->middleware('throttle:'.MagicLinkRateLimits::CONSUME_LIMITER)
        ->name('magic-link.consume');

    /*
    | Google and Microsoft SSO.
    |
    | {provider} is constrained at the controller by an explicit allowlist rather
    | than by a route pattern: the set of providers you may *sign in* with is
    | smaller than the set the platform connects to, and a `where()` pattern here
    | would drift out of step with that the moment a connect-only provider is
    | added.
    */
    Route::get('/auth/{provider}/redirect', [OauthLoginController::class, 'redirect'])
        ->name('oauth.redirect');

    Route::get('/auth/{provider}/callback', [OauthLoginController::class, 'callback'])
        ->name('oauth.callback');
});

/*
|--------------------------------------------------------------------------
| Second factors (`28` §9.1)
|--------------------------------------------------------------------------
|
| Authenticated, and outside every console group on purpose: mandatory 2FA is
| enforced by RequiresTwoFactor on the whole web group, so the screen that lets
| a person comply cannot live behind a gate that refuses them. Both routes are
| on that middleware's exemption list, by name.
|
| Open to every authenticated person, not only staff. A tenant is never
| *required* to hold a second factor — nothing in `29` asks for it and
| CLAUDE.md forbids adding a tenant-facing toggle in its place — but one who
| wants one should not have to be staff to get it, and SecondFactor::challenge()
| honours theirs on all four sign-in routes exactly as it does ours.
|
*/
Route::middleware('auth')->group(function (): void {
    Route::get('/security/two-factor', TwoFactorSetupController::class)
        ->name('two-factor.setup');

    /*
    | ⚠️ DECISION 660'S SECOND HALF. `Illuminate\Auth\Middleware\RequirePassword`
    | redirects to `route('password.confirm')`, Fortify registers that GET inside
    | `if ($enableViews)`, and `fortify.views` is false — so every endpoint the
    | enrolment screen posts to was unreachable: enable, confirm, the QR code,
    | the secret key and both recovery-code routes all sit behind that
    | middleware. The way in and the way through were dead for one reason, one
    | config flag apart.
    */
    Route::view(RoutePath::for('password.confirm', '/user/confirm-password'), 'auth.confirm-password')
        ->name('password.confirm');
});

/*
|--------------------------------------------------------------------------
| Inbound SMS (row 4 slice 2)
|--------------------------------------------------------------------------
|
| ⛔ THIS IS THE ENDPOINT THAT MAKES `sms.enabled` FLIPPABLE. Decision 1567:
| you may not send what you cannot stop. Until it existed, `opt_outs` had no
| writer from a real channel — 272's shape on the one table where being
| writerless is a legal exposure rather than an inert feature.
|
| ⚠️ EXEMPT FROM CSRF, like the Stripe webhook and for the same reason: a
| carrier cannot hold a token. What replaces it is HMAC-SHA256 over the raw
| body, verified against a key held in the credentials store — and unlike the
| Stripe integration, Infobip publishes NO fixed signing header name, so the
| header is configuration. `InfobipWebhookVerifier` carries the citations.
|
| ⚠️ NO `auth`, NO `throttle`, AND THE SECOND ABSENCE IS DELIBERATE. A rate
| limit on this path is a rate limit on other people's STOP messages: the
| carrier legitimately batches and retries, a burst is ordinary, and the
| failure mode of throttling is a discarded refusal. The signature is what
| makes an unthrottled endpoint safe, and it is checked before anything else.
|
*/
Route::post('/webhooks/infobip/inbound', InfobipInboundController::class)
    ->name('webhooks.infobip.inbound');

/*
|--------------------------------------------------------------------------
| Delivery receipts (row 4 slice 3)
|--------------------------------------------------------------------------
|
| What the carrier says happened to a message we sent. Without it every row
| sits `Queued` forever and the owner-facing log says "Waiting to send" about
| a message the customer read yesterday — decision 620's inversion, a reader
| showing a state nothing updates.
|
| ⚠️ A SEPARATE URL FROM THE INBOUND ONE, DELIBERATELY. Both payloads arrive
| as `results[]` carrying `messageId`, so a single endpoint would have to
| guess which it was holding from the fields present — and guessing wrong
| means treating a customer's STOP as a receipt. Two notification profiles,
| two URLs, no guess.
|
| Exempt from CSRF and signed by the same HMAC key as the inbound endpoint,
| named individually in `bootstrap/app.php` rather than by a wildcard.
|
*/
Route::post('/webhooks/infobip/delivery', InfobipDeliveryController::class)
    ->name('webhooks.infobip.delivery');

/*
|--------------------------------------------------------------------------
| Voice — the Calls event webhook (T176 P2, the voice half of R7)
|--------------------------------------------------------------------------
|
| ⛔ THIS IS THE ENDPOINT THAT MAKES THE MISSED-CALL CHAIN REAL. Until it
| existed, `CallMissed`, `VoicemailRecorded`, `MissedCallTextBack`,
| `TextBackMissedCaller` and `SendMissedCallTextBackJob` all existed and
| **nothing in `app/` dispatched any of the events** — 272's shape spread
| across a whole feature, with every gate tested and nothing feeding them.
|
| ⚠️ A THIRD URL, DELIBERATELY, for the reason the delivery entry gives one
| line up: three notification profiles, three URLs, no guessing which payload
| is which from the fields present.
|
| ⚠️ EXEMPT FROM CSRF AND SIGNED BY THE SAME HMAC KEY, verified before the
| body is read as anything but bytes. `InfobipWebhookVerifier` now knows both
| of Infobip's documented constructions — body-only and
| `timestamp . body` with `X-Ib-Exchange-Req-Signature` — because which one a
| notification profile uses is an account setting.
|
| ⛔ EXTERNAL GATE: Infobip Voice/Calls is NOT activated on the account
| (T176 §7 item 3). This route is live, verified and inert — `VOICE_DRIVER`
| seeds `null`, so a delivery is authenticated, queued, and answered
| `provider_unavailable`. Nothing in this repository can unblock that.
|
*/
Route::post('/webhooks/infobip/voice', InfobipVoiceController::class)
    ->name('webhooks.infobip.voice');

/*
|--------------------------------------------------------------------------
| Zernio review + account webhooks (row 3 slice I)
|--------------------------------------------------------------------------
|
| Push ingest for Google reviews, provider-side disconnect, and — since 6615 —
| `account.connected`, which corroborates a binding the redirect already made
| and never makes one itself. CSRF-exempt; HMAC-SHA256 over the raw body
| (`X-Zernio-Signature`), fail closed when the secret is unset — same shape as
| Infobip. Named individually in bootstrap/app.php rather than by a wildcard.
|
| ⚠️ **THE 6615 SLICE ADDED NO ROUTE AND ITS BRIEF EXPECTED ONE** (6769). This
| endpoint has existed since row 3 slice I and already verified signatures; what
| was missing was a `match` arm in `ZernioWebhooks`, not a door.
|
| ⚠️ **THE VENDOR ALLOWS 5 SECONDS FOR A 2xx** and retries anything else 7 times
| over ~51 hours before dead-lettering it (webhooks overview, read 2026-08-21).
| Every arm of this handler must stay inside that budget; the `account.connected`
| arm makes no vendor call for exactly that reason.
|
*/
Route::post('/webhooks/zernio', ZernioWebhookController::class)
    ->name('webhooks.zernio');

/*
|--------------------------------------------------------------------------
| SES bounce and complaint feed (row L4 — SL-4, open question H)
|--------------------------------------------------------------------------
|
| ⛔ THIS IS THE ENDPOINT THE FIRST CUSTOMER-FACING EMAIL IS BLOCKED ON.
| Decision 2069: "the webhook is the deliverable — not the vendor choice … H
| is not closed until the handler exists." 2094 then made Google Workspace the
| primary transport and reopened H on the hard side, because Gmail has no
| typed bounce or complaint event at all — so this endpoint is built, running,
| and silent until `MAIL_MAILER` points at SES.
|
| ⚠️ CSRF-EXEMPT, AND WHAT REPLACES THE TOKEN IS NOT AN HMAC. SNS shares no
| secret with us: it signs with a private key and names the certificate inside
| the message it signed. `SnsMessageVerifier` constrains that URL's host with
| `parse_url()` before fetching anything, checks the TopicArn against an
| allowlist, and fails closed when the allowlist is empty.
|
| ⚠️ NO `throttle`, on the inbound-SMS endpoint's reasoning: a bounce storm is
| the moment this feed matters most, SNS legitimately batches and retries, and
| the failure mode of throttling is a suppression that never lands.
|
*/
Route::post('/webhooks/ses', SesFeedbackController::class)
    ->name('webhooks.ses');

/*
|--------------------------------------------------------------------------
| Gmail push — inbound replies on the live transport (T137 §3 rail 3)
|--------------------------------------------------------------------------
|
| ⛔ WITHOUT THIS ROUTE A CUSTOMER'S REPLY REACHED NOTHING. `MailReplyRouter`
| had one caller — the SES endpoint above — and 2438 records that its topic
| allowlist seeds empty, so it accepts nothing. 2093 makes Gmail the primary
| send path. On the transport actually carrying mail there was no inbound
| route at all.
|
| ⚠️ CSRF-EXEMPT, AND WHAT REPLACES THE TOKEN IS A THIRD SHAPE AGAIN. Not an
| HMAC over the body (Infobip) and not a signature over a field list (SNS):
| Cloud Pub/Sub signs nothing in the request and attaches an OpenID Connect
| JWT in the `Authorization` header. `GooglePushTokenVerifier` checks the
| signature against Google's published keys, the `email` claim against the
| one service account we accept, the `aud` against this endpoint's own URL,
| and fails closed when either is unconfigured — the state a fresh install is
| in, so this exemption cannot leave an open endpoint behind it.
|
| ⚠️ AND A VERIFIED REQUEST IS STILL NOT A TRUSTED BODY. The controller acts
| on none of the payload beyond the mailbox address; the mail itself is read
| back from Gmail over our own connection.
|
| ⚠️ NO `throttle`, on the two neighbouring endpoints' reasoning: a burst of
| replies is the moment this feed matters most, and a throttled push is a
| nack, which Google answers by sending it again.
|
*/
Route::post('/webhooks/gmail', GmailPushController::class)
    ->name('webhooks.gmail');

/*
|--------------------------------------------------------------------------
| The review widget — the owner's door, and the bundle it hands out
|--------------------------------------------------------------------------
|
| ⛔ EVERY PROVISIONED WIDGET SERVED NOBODY FOR TEN DAYS BECAUSE THIS SCREEN DID
| NOT EXIST. Provisioning mints the plugin with an empty domain list,
| `originIsAllowed()` refuses an empty list — correctly — and
| `setAllowedDomains()` had no caller anywhere in `app/`. The API half has been
| live and tested since 2026-08-02 and answered 403 to every browser in the
| world. See decisions 2950–2969.
|
| `/account/website` is `auth` alone, for the reason `/account` gives above:
| `ResolveTenant` runs on the whole web group, so the component reads the tenant
| from the session and fails closed for a signed-in user with no business. No
| route parameter — the tenant is never in the URL (decision 396's rule).
|
| ⚠️ `/widget.js` IS PUBLIC, UNAUTHENTICATED AND CARRIES NO KEY, and all three
| are deliberate. It is one static file, identical for every tenant; the embed
| key travels in the snippet's `data-key` attribute and the script reads it at
| run time. A key in this path would turn one cached asset into a per-tenant one
| for no gain, and the tenant boundary lives on the *feed* this script calls
| (`routes/api.php`), never here.
|
| ⚠️ THE ADDRESS IS FIXED FOR EVER AND THE CONTENTS MOVE. It is pasted into
| websites we do not control and cannot edit, so a hashed filename here would
| break every install on the next build. `41` §3.2 calls this "versioned by
| immutable build hash behind the stable URL"; the controller is that hinge.
|
| Registered above the short-link catch-all out of tidiness rather than need —
| `[A-Za-z0-9]{12}` cannot match a name containing a dot.
|
*/
Route::middleware('auth')
    ->get('/account/website', AccountWidgetInstall::class)
    ->name('account.website');

Route::get('/widget.js', WidgetScriptController::class)
    ->name('widget.script');

Route::get('/chat.js', ChatScriptController::class)
    ->name('chat.script');

/*
|--------------------------------------------------------------------------
| The pixel bundle's delivery — `GOAIEZ_PIXEL_MASTER_BUILD.md` §10, decision
| 4980
|--------------------------------------------------------------------------
|
| ⚠️ `WidgetScriptController`'s note above does not apply here: `/widget.js`'s
| address is fixed and its contents move behind it, with no way to ask for an
| older one. The pixel needs the opposite property — a URL somebody can pin and
| get back, for ever, which is what `/v/<sha>/p.js`'s content address buys —
| because §10 asks for a rollback that is one command and under five minutes,
| and repointing `/p.js` at an address that has already changed once is not
| that.
|
| `/p.js` is `Route::get`'s ordinary case: it is what every install snippet
| names and it never appears with a path segment. `/v/{sha}/p.js` is
| constrained to exactly 64 lowercase hex characters — sha256's own width — so a
| malformed request 404s at routing rather than reaching `PixelDelivery` at all.
*/
Route::get('/p.js', [PixelBundleController::class, 'pointer'])
    ->name('pixel.bundle.pointer');

Route::get('/v/{sha}/p.js', [PixelBundleController::class, 'versioned'])
    ->where('sha', '[0-9a-f]{64}')
    ->name('pixel.bundle.versioned');

/*
|--------------------------------------------------------------------------
| T3 injection — the client module (BUILD-PLAN §2.11.3 slice I)
|--------------------------------------------------------------------------
|
| ⛔ A SECOND MODULE RATHER THAN AN EDIT TO THE COLLECTOR — §2.11.5 conflict 4,
| which is the architecture of that slice and not a note beside it. Teaching
| `pixel.js` to write to the DOM would put DOM-writing code in every visitor's
| browser on every tenant site whether or not actuation is on. This route serves
| that code **only to a tenant with live T3 change sets**, and an empty body to
| everybody else — including a paused account and a health-information tenant,
| both refused by the same service the payload route asks.
|
| ⚠️ THE KEY SELECTS WHETHER, NEVER WHAT. Every tenant who is served this gets
| identical bytes; a per-tenant JavaScript build would be code generated from
| data, which is the injection surface the typed payload exists to avoid.
|
| ⚠️ NOT A `/v/<sha>/` PAIR LIKE THE PIXEL ABOVE, AND `T3InjectionController`
| carries the argument: the pixel runs on every page of every tenant, this runs
| on the pages of tenants with live change sets, of which there are none until
| slice D publishes one.
*/
Route::get('/s/{key}.js', [T3InjectionController::class, 'module'])
    ->middleware('throttle:t3-module')
    ->name('actuation.t3.module');

/*
|--------------------------------------------------------------------------
| The pixel install screen — decision 4979 item (2)
|--------------------------------------------------------------------------
|
| ⛔ NOTHING HANDED A TENANT THEIR PUBLIC KEY OR THEIR SNIPPET UNTIL THIS
| ROUTE. `PixelKeys` had a reader (`resolve()`) for the collector's own use and
| a plain scoped read (`forBusiness()`) that nothing in `app/` ever called —
| the same shape `account.website` closes for `WidgetPlugins`, one screen over.
|
| `/account/tracking` is `auth` alone, `account.website`'s reasoning exactly:
| `ResolveTenant` runs on the whole web group, so the component reads the
| tenant from the session and fails closed for a signed-in user with no
| business. No route parameter — the tenant is never in the URL.
|
| ⚠️ THE URI DELIBERATELY NAMES NEITHER "pixel" NOR "ingest".
| `Architecture/PixelTest`'s first test asserts the *exact* set of routes
| whose URI matches either word is `['POST api/pixel/e']` — a second match
| reddens it by name, on purpose, so an ingest route cannot arrive quietly.
| This is a screen, not an ingest path, and its URI says so.
|
*/
Route::middleware('auth')
    ->get('/account/tracking', AccountPixelInstall::class)
    ->name('account.pixel-install');

Route::middleware('auth')
    ->get('/account/facts', AccountFacts::class)
    ->name('account.facts');

Route::middleware('auth')
    ->get('/account/maps-key', AccountPlacesKey::class)
    ->name('account.places-key');
/*
|--------------------------------------------------------------------------
| Where a tenant's own texting registration has got to (5329, 5420–5439)
|--------------------------------------------------------------------------
|
| ⛔ NOTHING TENANT-FACING RENDERED A REGISTRATION STATUS ANYWHERE UNTIL THIS
| ROUTE. 5329 verified it by grep — `BrandRegistration` appeared nowhere under
| `resources/` or `app/Livewire/` — so a tenant met `BroadcastPreconditions`'
| *"this account has no approved 10DLC registration of its own"* with no clock
| against it, and the honest days-language the law asks for *"wherever a tenant
| waits"* sat in an enum docblock no tenant reads.
|
| ⚠️ A READ AND NOTHING ELSE. Every writer of `brand_registrations` is still
| `sms:brand-registration`, Ops-only; this route resolves to a component with no
| public property and no action method, so there is nothing here for a browser to
| set. It is a status surface rather than the tenant-facing toggle `CLAUDE.md`
| forbids.
|
| `/account/texting` is `auth` alone, `account.pixel-install`'s reasoning exactly:
| `ResolveTenant` runs on the whole web group, so the component reads the tenant
| from the session and fails closed for a signed-in user with no business. No
| route parameter — the tenant never travels in a URL (decision 396's rule).
|
| ⚠️ THE URI NAMES NEITHER "10dlc" NOR "brand", AND THAT IS `22`'s OUTCOME RULE
| RATHER THAN CAUTION: a person is not visiting their brand registration, they
| are finding out whether they can text their own customers yet.
|
*/
Route::middleware('auth')
    ->get('/account/texting', AccountTexting::class)
    ->name('account.texting');

/*
|--------------------------------------------------------------------------
| Which location the account screens are acting on (3060–3079)
|--------------------------------------------------------------------------
|
| ⛔ THIS APPLICATION HAD NO LOCATION PICKER ANYWHERE, AND FOUR SCREENS WENT
| DARK BECAUSE OF IT (2969, and 1220's rule is what created the gap). A tenant
| paying $99.99/mo for a second location could not switch their review widget
| on by any route at all.
|
| ⚠️ A NAMED POST ROUTE RATHER THAN A LIVEWIRE ACTION, which is
| `account.plan.cancel`'s shape for its stated reason: `Livewire::test()` runs
| no middleware (809), so a Livewire action would leave `auth`, CSRF,
| `ResolveTenant` and the form request's `authorize()` unexercised by anything
| the suite runs. The tenant check on a picker is not a thing to leave as a
| claim.
|
| `auth` alone, for the reason `/account` gives above: `ResolveTenant` runs on
| the whole web group, so the request reads the tenant from the session and
| fails closed for a signed-in user with no business.
|
| ⚠️ NO ROUTE PARAMETER AND NO LOCATION IN ANY URL — decision 396's rule, and
| here it is also the persistence decision (3066). A selection carried in a URL
| is one that a shared link, a bookmark, a referer header or a pasted support
| ticket takes across a boundary it was never scoped to.
|
| ⚠️ NOT EXEMPTED FROM `SuspendedTenantStatus`. A suspended owner belongs at
| `/account/on-hold`, and changing which location they are looking at is not
| among the things decision 820 keeps reachable — unlike cancelling, which is.
|
*/
Route::middleware('auth')
    ->post('/account/location', SelectLocationController::class)
    ->name('account.location.select');

/*
|--------------------------------------------------------------------------
| The short-link redirector (T137 SL-5)
|--------------------------------------------------------------------------
|
| `https://goaiez.ai/{token}` — the address that goes in an SMS, where every
| character is charged against the 159 a segment allows (2116).
|
| ⚠️ REGISTERED LAST, AND CONSTRAINED TO THE TOKEN'S EXACT SHAPE. A catch-all
| at the application root would shadow every route that ever gets added after
| it; twelve base62 characters cannot collide with `/pricing` or `/f/{slug}`,
| and Laravel matches in registration order, so every real route above wins
| regardless.
|
| ⚠️ THE HOST IS CHECKED IN THE CONTROLLER RATHER THAN WITH `Route::domain()`.
| Route registration happens before a database read is affordable, so a domain
| constraint here would have to come from config while links are minted from
| the registry seed — two values that drift silently into links that resolve
| nowhere. See the controller for the whole argument.
|
| Throttled per visitor on a hashed address; the tighter miss budget lives in
| `ShortLinkRateLimits` because only a failed resolve is worth counting.
|
*/
/*
|--------------------------------------------------------------------------
| X-140 Proposed Pages
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth'])
    ->get('/account/content-topics', ProposedPagesView::class)
    ->name('account.content-topics');

Route::middleware(['web', 'auth'])
    ->get('/prospects/top3-preview/{prospectId}', ProspecttenantfacingTop3Preview::class)
    ->name('prospects.top3-preview');

Route::middleware(['web', 'auth'])
    ->get('/prospects/match-scores/{prospectId}', MatchScores::class)
    ->name('prospects.match-scores');

Route::get('/{token}', ShortLinkController::class)
    ->where('token', '[A-Za-z0-9]{'.ShortLinks::TOKEN_LENGTH.'}')
    ->middleware('throttle:short-link')
    ->name('short-link.resolve');
