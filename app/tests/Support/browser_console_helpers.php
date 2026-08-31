<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

/*
|--------------------------------------------------------------------------
| A browser console collector that is not blind — shared by every Browser file
|--------------------------------------------------------------------------
|
| ⛔ WHAT THE PLUGIN'S OWN ASSERTION SEES, MEASURED RATHER THAN READ (10200).
| `Pest\Browser\Playwright\InitScript` installs exactly two hooks: it wraps
| `console.log`, and it registers `window.addEventListener('error', …)` in the
| BUBBLE phase. Five defect shapes were planted on a fixture page and
| `assertNoJavaScriptErrors()` was asked about each:
|
|     console.error('…')                     → jsErrors [] → GREEN
|     console.warn('…')                      → jsErrors [] → GREEN
|     Promise.reject(new Error('…'))         → jsErrors [] → GREEN
|     <script src="/missing.js">   (404)     → jsErrors [] → GREEN
|     throw new Error('…')                   → jsErrors [1] → RED
|
| So it is blind to four of the five, and the one it catches is the one a
| framework almost never produces. `assertNoConsoleLogs()` is `console.log`
| only, and `assertNoSmoke()` is those two and nothing more.
|
| ⚠️ THE MECHANISM FOR THE 404 IS THE CAPTURE PHASE, AND IT WAS MEASURED TOO.
| A failed resource load fires `error` ON THE ELEMENT and that event DOES NOT
| BUBBLE, so a bubble-phase listener on `window` never runs. Two listeners on
| one page, one bubble and one capture, over a 404ing `<script>` and a 404ing
| `<img>`:
|
|     bubble  → []
|     capture → ['resource:SCRIPT', 'resource:IMG']
|
| ⛔ That is why the listener below passes `true` as the third argument, and it
| is the half `tests/Browser/AccountScreenTest.php`'s own wave-33 collector is
| still missing — it registers in the bubble phase, so a broken bundle URL on
| the account screens is invisible to it as well.
|
| ---------------------------------------------------------------------------
| ⛔ WHAT THIS FILE REFUSES TO DO, AND WHY IT MATTERS MORE THAN WHAT IT DOES
| ---------------------------------------------------------------------------
| A collector that was never installed reads back as a collector that saw
| nothing. `window.__browserConsole` would be `undefined`, `… || []` would be
| `[]`, and the assertion would be GREEN on a page it had never watched — which
| is precisely the failure this file exists to end, rebuilt one layer up.
|
| So `browserConsoleFindings()` asks whether the collector is PRESENT before it
| asks what it saw, and THROWS when it is not. There is no arm in which a
| missing instrument reports health.
|
| ⚠️ THE FINDINGS ARE PER DOCUMENT. An init script runs afresh on every
| navigation, so a `navigate()` discards what the previous document collected.
| Assert before you navigate away, or accept that you are asserting about the
| last page only. Wave 33's collector has the same property for the same reason.
|
| ⚠️ AND IT IS HALF OF A PAIR (10107). This proves the BUNDLE behaved on a page
| a real browser rendered; it cannot prove any line is written in
| `resources/js/`, and it will report health about a `public/build` that is
| weeks older than the working tree. Run `./node_modules/.bin/vite build` first.
*/

/**
 * The collector, as JavaScript, for composing into an existing init script.
 *
 * Exposed as a string rather than only as a helper because two Browser files
 * already build their own init script and take a fragment
 * (`pixelOnAPageWatchingTheWire()`'s `$init`), and a second `addInitScript()`
 * call after a navigation attaches too late to be worth anything.
 */
function browserConsoleWatchScript(): string
{
    return <<<'JS'
        window.__browserConsole = {
            installed: true,
            consoleError: [],
            consoleWarn: [],
            scriptError: [],
            resourceError: [],
            rejection: [],
        };

        (function () {
            var realError = console.error;
            var realWarn = console.warn;

            console.error = function () {
                var args = Array.prototype.slice.call(arguments);
                window.__browserConsole.consoleError.push(args.map(String).join(' '));
                realError.apply(console, args);
            };

            console.warn = function () {
                var args = Array.prototype.slice.call(arguments);
                window.__browserConsole.consoleWarn.push(args.map(String).join(' '));
                realWarn.apply(console, args);
            };

            // ⛔ CAPTURE PHASE. A resource `error` does not bubble; see the
            // header. One capture-phase listener sees both kinds, because an
            // uncaught script error targets `window` itself and therefore
            // reaches this listener at target whatever the phase flag says.
            window.addEventListener('error', function (event) {
                if (event.target && event.target !== window && event.target.tagName) {
                    var url = event.target.src || event.target.href || '(no url)';
                    window.__browserConsole.resourceError.push(event.target.tagName + ' failed to load: ' + url);

                    return;
                }

                window.__browserConsole.scriptError.push(String(event.message));
            }, true);

            window.addEventListener('unhandledrejection', function (event) {
                var reason = event.reason;

                try {
                    reason = reason && reason.message ? reason.message : JSON.stringify(reason);
                } catch (e) {
                    reason = String(reason);
                }

                window.__browserConsole.rejection.push(String(reason));
            });
        })();
        JS;
}

/**
 * Land somewhere harmless, install the collector, then navigate.
 *
 * The two-step is `AccountScreenTest`'s and `PixelOnAPageTest`'s pattern and is
 * there for their reason: an init script attaches to a browser CONTEXT, so it
 * has to be in place before the page under test has ever run a line. The first
 * document is therefore UNWATCHED by construction, which is why `$landOn`
 * defaults to the marketing home rather than to the page under test.
 *
 * @param  string  $extraInit  More JavaScript for the same init script, run
 *                             after the collector so it can be observed by it.
 * @param  string  $landOn  Somewhere cheap that is not the subject. A caller
 *                          with its own fixture page should pass it.
 */
function browserConsoleVisit(string $path, string $extraInit = '', string $landOn = '/'): mixed
{
    $page = browserConsoleWatch(visit($landOn), $extraInit);

    $page->navigate($path);

    return $page;
}

/**
 * The same, at the width `29` §5.5 / `22` actually names — 320px.
 *
 * ⛔ EVERY CALLER OF THIS FUNCTION IS A TEST NAMED "AT 320PX", AND UNTIL
 * WAVE 37 LANE B NOT ONE OF THEM MEASURED IT — measured, not theorised
 * (10443). This used `->on()->mobile()`, which is `Device::MOBILE`:
 * 375×812. `pest-plugin-browser` ships a real preset at the exact width the
 * spec names — `Device::IPHONE_SE`, 320×568, `isMobile: true`,
 * `hasTouch: true` — and it is what this function now uses. It is still an
 * emulated Chromium, not the "real mid-range Android" `22` asks for; see
 * `docs/DECISIONS.md` 10443 for what that gap still leaves unmeasured.
 *
 * ⛔ TWO VENDOR TRAPS ARE PAID FOR HERE SO NO CALLER MEETS EITHER.
 *
 * The first is that `on()->iPhoneSE()` returns a BUILDER rather than a page,
 * and `On::__call()` constructs a fresh `PendingAwaitablePage` for EVERY
 * method called on it — so `$on->page()` and `$on->navigate()` act on two
 * different browser pages, the init script lands on one the test never looks
 * at, and the navigation appears not to have happened. Driven, not read. One
 * real assertion collapses the builder into one `Webpage`, and everything
 * after it is that page.
 *
 * The second is which assertion. ⚠️ `assertPresent('body')` FINDS NOTHING:
 * `GuessLocator` treats a selector with no CSS special character as an id, then
 * a name, then TEXT — so a bare tag name never selects by tag, and the failure
 * reads as though the page were empty. `html > body` contains `>`, which is
 * what makes `Selector::isExplicit()` treat it as CSS.
 */
function browserConsoleVisitOnMobile(string $path, string $landOn, string $extraInit = ''): mixed
{
    $page = visit($landOn)->on()->iPhoneSE()->assertPresent('html > body');

    browserConsoleWatch($page, $extraInit);

    $page->navigate($path);

    return $page;
}

/**
 * Install the collector on an ALREADY-CREATED page, for the caller to navigate.
 *
 * ⚠️ THIS EXISTS FOR `->on()->mobile()` AND ITS SIBLINGS. `visit()` is lazy —
 * the browser page is not built until something asks for it — and `on()`
 * returns a fresh builder each time, so `browserConsoleVisit()` cannot take a
 * device without growing a parameter for every one of them. The caller builds
 * the page it wants, hands it here, and navigates.
 *
 * ⛔ THE PAGE HANDED IN HAS ALREADY LOADED ITS FIRST DOCUMENT and that one is
 * unwatched. Land somewhere that is not the subject, exactly as
 * `browserConsoleVisit()` does.
 */
function browserConsoleWatch(mixed $page, string $extraInit = ''): mixed
{
    $page->page()->context()->addInitScript(browserConsoleWatchScript()."\n".$extraInit);

    return $page;
}

/**
 * Everything the collector saw, one line each, newest last.
 *
 * @return list<string>
 *
 * @throws RuntimeException when the collector is not on the page at all
 */
function browserConsoleFindings(mixed $page): array
{
    $raw = $page->script('window.__browserConsole ? JSON.stringify(window.__browserConsole) : null');

    if (! is_string($raw)) {
        throw new RuntimeException(
            'The wide console collector is not installed on this page, so nothing can be said '
            .'about whether it is healthy. Reach it through browserConsoleVisit(), or compose '
            .'browserConsoleWatchScript() into the init script this test already attaches — and '
            .'attach it BEFORE the navigation, because an init script that arrives afterwards '
            ."watched nothing.\nPage: ".(string) $page->script('window.location.href')
        );
    }

    /** @var array{installed: bool, consoleError: list<string>, consoleWarn: list<string>, scriptError: list<string>, resourceError: list<string>, rejection: list<string>} $collected */
    $collected = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $findings = [];

    foreach (['scriptError', 'rejection', 'resourceError', 'consoleError', 'consoleWarn'] as $kind) {
        foreach ($collected[$kind] as $line) {
            $findings[] = $kind.': '.$line;
        }
    }

    return $findings;
}

/**
 * Fail unless the browser reported nothing at all on this document.
 *
 * ⚠️ `$what` names the page in the human's words, because the failure a person
 * reads is *"the owner's plan screen"* and never a URL they have to resolve.
 */
function assertBrowserConsoleClean(mixed $page, string $what): void
{
    assertBrowserConsoleReports($page, $what, []);
}

/**
 * Fail unless the browser reported EXACTLY these lines and no others.
 *
 * ⛔ AN EXACT LIST RATHER THAN AN ALLOWLIST, AND THE DIFFERENCE IS THE WHOLE
 * REASON THIS EXISTS. An allowlist answers *"is everything here permitted?"*,
 * which stays green when a permitted line stops being emitted — so an exemption
 * for a vendor warning survives the vendor's script no longer loading at all,
 * which is the state it was written to make visible. Exact equality carries its
 * own guard-on-the-guard: the entry reddens when the line vanishes as readily as
 * when a new one appears, and nobody has to remember to write the floor.
 *
 * @param  list<string>  $expected  In the order `browserConsoleFindings()`
 *                                  produces: script errors, rejections,
 *                                  resource failures, `console.error`, then
 *                                  `console.warn`.
 */
function assertBrowserConsoleReports(mixed $page, string $what, array $expected): void
{
    $findings = browserConsoleFindings($page);

    Assert::assertSame(
        $expected,
        $findings,
        'The browser said something different from what was expected on '.$what
        ."\n\nWhat it said:\n  - ".($findings === [] ? '(nothing)' : implode("\n  - ", $findings))
        ."\n\nWhat was expected:\n  - ".($expected === [] ? '(nothing)' : implode("\n  - ", $expected))
        ."\n\n⚠️ The plugin's own assertNoJavaScriptErrors() sees none of these except a bare "
        .'uncaught throw, so a green run of it says nothing about any of the lines above.'
    );
}

/**
 * Poll the page until an expression is true, or fail saying what was on screen.
 *
 * ⛔ CONSOLIDATED HERE FROM SEVEN BYTE-IDENTICAL COPIES — wave 37 lane B
 * (`docs/DECISIONS.md` 10309, proposed by a wave-36 lane and not done there
 * because the move needed a file that lane did not own this wave). Every
 * Browser test file needed this and none of them could reach a plain
 * function declared in another file in `tests/Browser/`, so `_COMMON.md`'s
 * own instruction was to copy it under a file-local name rather than edit a
 * file outside a lane's slice. `tests/Support/` is different: `tests/Pest.php`
 * `require_once`s every file in it before any test runs, which is why
 * `browserTestSources()`'s own warning about a global helper declared in one
 * TEST file and called from another (a load-order hazard — 694, 808) does
 * not apply to a function declared HERE. This file is already `require_once`d
 * the same way `architecture_helpers.php`, `tenant_helpers.php`,
 * `pixel_helpers.php` and `warehouse_helpers.php` are.
 *
 * The name is kept rather than generalised, because `tests/Browser/
 * AccountScreenTest.php`'s own pin — *"the browser plugin still cannot wait
 * for a condition, so accountScreenAwait stays"* — names this function in
 * its own test title, and a rename would have made that title as stale as
 * the thing it is meant to catch.
 *
 * `Playwright\Page::waitForFunction()` returns immediately and throws
 * nothing — `expression` and `arg` are sent and no `isFunction`, `polling`
 * or `timeout`, measured at 10105 against a predicate that can never become
 * true. `assertSee()` does not retry either, calling `isVisible()` once.
 */
function accountScreenAwait(mixed $page, string $expression, string $what): void
{
    $deadline = microtime(true) + 10.0;

    do {
        if ($page->script($expression) === true) {
            return;
        }

        usleep(100_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException(
        "Waited 10s for {$what} and it never happened. What the page showed:\n\n"
        .(string) $page->script('document.body.innerText')
    );
}

/*
|--------------------------------------------------------------------------
| WCAG 2.2 AA colour contrast — wave 39 lane E, docs/DECISIONS.md 10780+
|--------------------------------------------------------------------------
|
| ⛔ CLOSES A GAP `tests/Feature/ComponentLibraryTest.php` NAMES BUT CANNOT
| CLOSE: "contrast ratios and LCP need a browser, and asserting them in PHP
| would produce a test that passes while the page fails." That sentence is
| still correct about PHP; it was never an argument against a browser doing
| the measurement, and nothing in this suite did until now.
|
| ⚠️ SCOPED TO EXACTLY ONE AXE-CORE RULE, DELIBERATELY. A full axe audit
| answers questions this repository has not asked yet (ARIA structure, focus
| order, landmark regions, …) and would report on the order of hundreds of
| findings on a normal page — "a check nobody reads" (decision 511). The
| owner's dependency approval is for the census this lane's brief describes:
| contrast. `runOnly` is set to the single `color-contrast` rule, which is
| the WCAG 2.2 §1.4.3 test this design-token system has never had measured.
| Widening the ruleset is a decision for whoever owns THAT gap, made with
| its own argument about what it gates and what it merely reports.
|
| ⚠️ axe-core (MPL-2.0), not `@axe-core/playwright`. The latter's `AxeBuilder`
| is a Node-side class built to drive Playwright's own JS API directly; this
| suite drives Playwright from PHP through `pest-plugin-browser`'s own
| protocol bridge, which never runs Node test code of its own — so there is
| no place `AxeBuilder` could run from. `Page::evaluate()` (this file's
| `$page->script()`) is the only surface available, and it is all injecting
| the plain axe-core engine needs: load the source, call `axe.run()`, read
| the result back as JSON. `@axe-core/playwright` would have bought a
| convenience API this architecture cannot call and left the same injection
| to write by hand regardless.
*/

/**
 * axe-core's `color-contrast` rule, run against the CURRENT document.
 *
 * @return list<array{id: string, impact: ?string, help: string, nodes: list<array{target: string, summary: ?string}>}>
 */
function axeContrastViolations(mixed $page): array
{
    static $axeSource = null;

    // Read once per process, not once per call — the file is ~570KB and
    // every caller in a single test run wants the same bytes.
    $axeSource ??= file_get_contents(base_path('node_modules/axe-core/axe.min.js'));

    if ($axeSource === false || $axeSource === '') {
        throw new RuntimeException(
            'axe-core is not installed at node_modules/axe-core/axe.min.js — run `npm ci`.'
        );
    }

    // A single IIFE expression, matching this file's own `.script()`
    // convention (`evaluateExpression` over the Playwright bridge takes an
    // expression, not an arbitrary sequence of statements) — axe-core's own
    // UMD wrapper attaches to `window`, so it is reachable from inside the
    // nested function scope regardless of where the source is evaluated.
    $raw = $page->script(<<<JS
        (function () {
            {$axeSource}

            return axe.run(document, {
                runOnly: {type: 'rule', values: ['color-contrast']},
            }).then(function (results) {
                return JSON.stringify(results.violations.map(function (violation) {
                    return {
                        id: violation.id,
                        impact: violation.impact,
                        help: violation.help,
                        nodes: violation.nodes.map(function (node) {
                            return {
                                target: node.target.join(' '),
                                summary: node.failureSummary,
                            };
                        }),
                    };
                }));
            });
        })()
    JS);

    if (! is_string($raw)) {
        throw new RuntimeException('axe.run() did not return a JSON string — got: '.var_export($raw, true));
    }

    /** @var list<array{id: string, impact: ?string, help: string, nodes: list<array{target: string, summary: ?string}>}> $violations */
    $violations = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    return $violations;
}

/**
 * Fail unless axe-core's `color-contrast` rule found nothing on this page.
 *
 * ⚠️ `$what` names the page in the human's words, matching
 * `assertBrowserConsoleClean()`'s own convention — the failure a person
 * reads names the screen, not a URL they have to resolve.
 */
function assertNoContrastViolations(mixed $page, string $what): void
{
    $violations = axeContrastViolations($page);

    Assert::assertSame(
        [],
        $violations,
        "axe-core found a WCAG 2.2 AA colour-contrast violation on {$what}\n\n"
        .json_encode($violations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );
}

/*
|--------------------------------------------------------------------------
| Largest Contentful Paint on an ALREADY-NAVIGATED page — wave 39 lane E
|--------------------------------------------------------------------------
|
| Extracted from `tests/Browser/MarketingHomeTest.php`'s own
| `largestContentfulPaint(string $url)`, which does a bare `visit($url)`
| and can only ever measure a page reached by a first load. Authenticated
| screens in this suite are reached by the two-step
| `browserConsoleVisit()`/`browserConsoleVisitOnMobile()` pattern — land
| somewhere harmless, THEN `navigate()` to the subject — so this version
| takes an already-built, already-navigated `$page` instead of a URL.
|
| ⛔ WHETHER `buffered: true` SEES THE LCP ENTRY OF A DOCUMENT REACHED BY
| `navigate()` RATHER THAN A FIRST LOAD IS SETTLED, NOT ASSUMED (10780).
| `navigate()` is `Page::goto()` under the hood — a genuine Playwright
| navigation, not a client-side transition (this application has none —
| "no client-side router" is the whole point of the Livewire choice) — so
| the browser's own Performance Timeline for the new document is exactly
| as real as one reached by `visit()`'s first load. Confirmed with a real,
| one-off scratch probe before this function was written (a fresh
| `browserConsoleVisit('/home')` reported a real LCP entry), never
| committed.
*/

/**
 * The browser's own LCP entry for the CURRENT document, or null if it
 * never reported one.
 *
 * `buffered: true` is load-bearing, exactly as it is in
 * `MarketingHomeTest.php`'s sibling function: the observer is attached
 * after the page has already painted, and without it every entry that
 * matters has been and gone.
 *
 * @return array{ms: float, tag: ?string, id: ?string, text: ?string}|null
 */
function pageLargestContentfulPaint(mixed $page): ?array
{
    /** @var array{ms: float, tag: ?string, id: ?string, text: ?string}|null $entry */
    $entry = $page->script(<<<'JS'
        new Promise((resolve) => {
            let latest = null;

            new PerformanceObserver((list) => {
                const entries = list.getEntries();
                latest = entries[entries.length - 1];
            }).observe({ type: 'largest-contentful-paint', buffered: true });

            // LCP is only final once the page stops producing bigger candidates.
            // A beat after load is enough here for the same reason
            // MarketingHomeTest.php gives: nothing on these screens loads late
            // by design — that is the property being tested.
            setTimeout(() => {
                if (!latest) return resolve(null);

                resolve({
                    ms: latest.startTime,
                    tag: latest.element ? latest.element.tagName : null,
                    id: latest.element ? latest.element.id : null,
                    text: latest.element ? (latest.element.textContent || '').trim().slice(0, 60) : null,
                });
            }, 750);
        })
    JS);

    return $entry;
}
