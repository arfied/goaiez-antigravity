<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\Location;
use App\Models\Plugin;
use App\Services\Tenant\LocationContext;
use App\Services\Widgets\WidgetInstalls;
use App\Services\Widgets\WidgetPlugins;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The door on the review widget — where an owner names their website.
 *
 * ⛔ **EVERY PROVISIONED WIDGET REFUSED EVERY BROWSER REQUEST, PERMANENTLY, FROM
 * 2026-08-02 TO 2026-08-12.** `TenantProvisioner` mints the plugin row with
 * `allowed_domains => []`, `WidgetPlugins::originIsAllowed()` returns `false` on
 * an empty allowlist — correctly, that is the direction a cross-origin allowlist
 * has to fail — and **`setAllowedDomains()` had no caller anywhere in `app/`**.
 * Its only callers were the three in `WidgetFeedTest`. So the feed answered 403
 * for every tenant that has ever existed, and no test could see it: a test that
 * calls the setter itself proves the *setter* works and says nothing whatsoever
 * about whether anything calls it. Decision 272's shape, the eighteenth
 * instance, and this component is the writer.
 *
 * ⛔ **`setMinStars()` IS THE SAME SHAPE AND IS DELIBERATELY LEFT WITHOUT ONE**
 * (2951–2955). It is a standing rating filter on a **public** surface, which is
 * what FTC 16 CFR §465.7 calls review suppression, and — decisively — the feed's
 * query is source-agnostic, so raising it hides low-rated **Google** reviews,
 * which `29` §2 rule 1 says is never to be built. See the decision block; the
 * refusal is held by a lint in `Architecture/WidgetTest` rather than by this
 * paragraph.
 *
 * ⚠️ **IT WRITES NOTHING ITSELF.** `Account\ReviewRules`' rule: `WidgetPlugins`
 * is the only reader and writer of `plugins` — held by a lint in
 * `Architecture/ReviewsTest`, which is why even the *read* on this screen goes
 * through `forLocation()`. The normalisation, the audit entry and the tenant
 * check all belong to the service, so a second screen cannot become a second set
 * of rules.
 *
 * ⚠️ **AND IT STILL WRITES NOTHING, WHICH WAS RE-CHECKED RATHER THAN INHERITED
 * FROM THE PARAGRAPH ABOVE** (3094). This screen now also *reads* whether the
 * pasted line is actually working — 2969's other half — and the writer for that
 * is the public feed request itself (3080), not anything a signed-in person
 * does here. The read goes through `WidgetInstalls` for the same reason the
 * plugin read goes through `WidgetPlugins`: one service owns the staleness
 * window, so a second screen cannot become a second definition of "installed".
 *
 * ⚠️ **"NOT SEEN YET" IS NOT "NOT INSTALLED", AND THE COPY MUST NEVER SAY IT IS**
 * (3084). Verification observes a real visitor loading a real page, so a
 * correctly pasted snippet on a page nobody has opened is indistinguishable from
 * no snippet at all. The screen names the action that resolves it — open your
 * own website — instead of telling somebody who did the work that they failed.
 *
 * ⚠️ **VALIDATION IS INLINE RATHER THAN IN A FORM REQUEST**, which is
 * `Account\Knowledge`'s and `Account\ReviewRules`' shape and not a departure: a
 * `FormRequest` is resolved out of the container for an HTTP controller action,
 * and a Livewire action is neither. `$this->validate()` is the framework's own
 * answer here.
 *
 * ⚠️ **THE FIELD IS RE-READ FROM WHAT WAS STORED AFTER EVERY SAVE, AND THAT IS
 * LOAD-BEARING.** The rule below and `WidgetPlugins::normaliseHost()` are two
 * parsers of the same string, and two parsers drift. The service is the one that
 * decides; a value that passes validation here and is dropped there would
 * otherwise vanish silently while the box still showed it. Re-reading makes the
 * screen show what is true rather than what was typed.
 *
 * AUTHORIZATION IS A POLICY (`CLAUDE.md`). Anyone signed in may read the snippet
 * — the person pasting a line into a website is often not the person who decides
 * policy — and only `canConfigureAutomation()` may change where the feed serves.
 */
#[Layout('components.account.layout')]
final class WidgetInstall extends Component
{
    /**
     * How many websites one feed may serve.
     *
     * A bound rather than a considered maximum: this is a `jsonb` column and a
     * loop in `originIsAllowed()` that runs on every public feed request, so an
     * unbounded list is a public endpoint whose cost a tenant chooses. Twenty is
     * far past any real answer — a business has a website, sometimes a second
     * for a campaign — and a tenant who genuinely needs more has something we
     * should hear about rather than absorb silently.
     */
    public const int MAX_DOMAINS = 20;

    /**
     * One host per line, held as the raw text of the box.
     *
     * A textarea rather than repeaters or a tag input: the whole answer is
     * usually one line, and a control with add/remove buttons is a support
     * surface for a field somebody edits twice a year.
     */
    public string $domains = '';

    public function mount(WidgetPlugins $plugins): void
    {
        $plugin = $this->plugin($plugins);

        if ($plugin instanceof Plugin) {
            $this->domains = $this->asText($plugin);
        }
    }

    public function save(WidgetPlugins $plugins): void
    {
        $plugin = $this->plugin($plugins);

        abort_if(! $plugin instanceof Plugin, 404);

        // Authorization before validation, `Account\Knowledge`'s reasoning:
        // telling somebody their domain is malformed and then refusing them for
        // their role is two errors for one action, and the second is the one
        // that mattered.
        Gate::authorize('update', $plugin);

        $this->validate([
            'domains' => ['nullable', 'string', 'max:2000'],
        ]);

        $lines = $this->lines();

        if (count($lines) > self::MAX_DOMAINS) {
            $this->addError('domains', 'That is more than '.self::MAX_DOMAINS
                .' websites. Tell us what you are doing and we will sort it out.');

            return;
        }

        foreach ($lines as $line) {
            // Deliberately permissive about shape and strict about *being* a
            // host: somebody will paste `https://ledger.test/reviews` out of the
            // address bar, and the service parses the host out of exactly that.
            // What this refuses is a sentence, a bare word with no dot, and
            // anything with a space in it — the answers that would otherwise be
            // dropped without a word.
            if (preg_match('/^(https?:\/\/)?[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(:\d+)?(\/.*)?$/i', $line) !== 1) {
                $this->addError('domains', '“'.$line.'” does not look like a website address. '
                    .'Write it the way it appears in the address bar, like ledger.test, '
                    .'one per line.');

                return;
            }
        }

        // The service normalises, de-duplicates, writes and audits. This screen
        // hands it strings and nothing else.
        $plugin = $plugins->setAllowedDomains($plugin, $lines, 'user:'.(auth()->id() ?? 'unknown'));

        // What is actually stored, not what was typed — see the docblock.
        $this->domains = $this->asText($plugin);

        // Outcome language, and no personal data in the toast (104).
        Toaster::success($lines === []
            ? 'Saved — your reviews are not showing on any website'
            : 'Saved — your reviews can show on the websites you listed');
    }

    public function render(WidgetPlugins $plugins, WidgetInstalls $installs): View
    {
        // Refused rather than resolved when there is no tenant — `Knowledge`'s
        // reasoning exactly: internal staff belong to no business by design, so
        // a signed-in support agent typing this URL is the ordinary way to
        // arrive with nothing resolved, and letting `Tenancy::idOrFail()` reach
        // the renderer is a 500 that reads as our page being broken.
        abort_if(Tenancy::id() === null, 403);

        $plugin = $this->plugin($plugins);

        return view('livewire.account.widget-install', [
            'available' => $plugin instanceof Plugin,
            'mayEdit' => $plugin instanceof Plugin && Gate::allows('update', $plugin),
            'snippet' => $plugin instanceof Plugin ? $this->snippet($plugin) : null,
            'live' => $plugin instanceof Plugin && $plugin->allowed_domains !== [],
            // The picker renders itself away for a single-location tenant, so
            // these are passed unconditionally — see the component.
            'locationOptions' => $this->locations()->options(),
            'selectedLocation' => $this->locations()->current(),
            // ⚠️ **THE READ HALF OF 2969'S GAP** (3080). Shown to everyone who
            // may see the snippet, on 2965's reasoning: the person who pasted
            // the line is very often not the person who may change policy, and
            // they are exactly who needs to know whether it worked.
            'install' => $plugin instanceof Plugin ? $installs->statusFor($plugin) : null,
        ]);
    }

    /**
     * The line an owner pastes into their website.
     *
     * ⚠️ **A STABLE URL WITH THE BUILD HASH BEHIND IT** (`41` §3.2), never
     * `Vite::asset()`. A hashed filename in this string would mean the line a
     * tenant pasted last year stops resolving the next time we build, on a page
     * we do not control and cannot edit. `WidgetScriptController` is what puts
     * the current bundle behind the fixed address.
     *
     * `41` §3.2 names `w.goaiez.com`; this serves from the application's own
     * origin instead, because that subdomain does not exist and a snippet
     * pointing at a host nobody has configured is a screen that lies (2958).
     */
    private function snippet(Plugin $plugin): string
    {
        return sprintf(
            '<script async src="%s" data-key="%s"></script>',
            route('widget.script'),
            $plugin->embed_key,
        );
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R/', $this->domains) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }

    private function asText(Plugin $plugin): string
    {
        $stored = $plugin->allowed_domains ?? [];

        return implode("\n", array_map(strval(...), array_filter($stored, is_string(...))));
    }

    /**
     * The review feed of the location this screen is acting on.
     *
     * ⛔ **THIS RETURNED NULL FOR EVERY MULTI-LOCATION TENANT UNTIL 3060–3079,
     * WHICH IS 2969's DEBT** — *"a multi-location tenant has no screen at all…
     * such a tenant currently cannot switch their widget on by any route."* A
     * plugin is minted per location, there was no picker anywhere, and 1220's
     * rule made the panel absent rather than let it guess. That was the right
     * call while there was nothing to disambiguate with, and it left a customer
     * paying $99.99/mo for a second location with no route to their own feed.
     *
     * ⚠️ **THE GUESS IS STILL REFUSED — WHAT IS GONE IS THE SILENCE** (3067).
     * `LocationContext::current()` returns a deterministically ordered default
     * rather than an arbitrary one, and `<x-account.location-picker>` names it
     * on the page. A default the owner can see and change is a different object
     * from one they cannot, and the lint in `Architecture/AccountScreensTest`
     * is what stops the two being separated by a later edit.
     *
     * ⚠️ AND STILL NEVER `sole()`. A `ModelNotFoundException` here takes the
     * whole page down; `ReviewRules` records three unrelated tests catching
     * exactly that (1433).
     */
    private function plugin(WidgetPlugins $plugins): ?Plugin
    {
        abort_if(Tenancy::id() === null, 403);

        $location = $this->locations()->current();

        return $location instanceof Location ? $plugins->forLocation($location) : null;
    }

    private function locations(): LocationContext
    {
        return app(LocationContext::class);
    }
}
