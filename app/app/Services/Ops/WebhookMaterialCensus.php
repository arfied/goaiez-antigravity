<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Console\Commands\ShowWebhookMaterial;
use App\Contracts\VerifiesWebhookSenders;
use App\Enums\PlatformHealthSignal;
use App\Enums\WebhookVerification;
use App\Services\Config\CredentialStore;
use App\Services\Sms\InfobipWebhookVerifier;
use App\Support\CredentialManifest;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteAction;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Whether every webhook endpoint this application answers holds the material it
 * verifies callers with — asked **at rest, with zero traffic** (11640–11651).
 *
 * ## ⛔ The fault this exists for is knowable at rest and nothing asked
 *
 * ⛔ **`credentials.infobip_webhook_secret` IS UNSET ON THE RUNNING INSTALL,
 * EVERY GENUINE INBOUND DELIVERY IS ANSWERED 401 BEFORE THE BODY IS READ, AND A
 * CUSTOMER WHO TEXTS STOP IS NEVER RECORDED AND NEVER SUPPRESSED.** Nothing in
 * this application could notice. {@see CredentialStore::resolve()} records
 * {@see PlatformHealthSignal::CredentialAbsent} **on a read**, and
 * the only reader of that key in `app/` is
 * {@see InfobipWebhookVerifier}, reached only from the three
 * Infobip controllers — so with the key unset, `PlatformHealth` holds no row,
 * {@see PlatformHealthChecks} iterates zero sources, and the five-minute sweep
 * raises nothing **for ever, until an inbound message arrives**. ⛔ **The first
 * person to text STOP is the one who pays**, and that is true of all four
 * credential-backed endpoints rather than only that one.
 *
 * ⚠️ **NOTHING HERE SETS IT.** This makes the absence loud where it was silent;
 * the value is the owner's to paste into Ops → Platform → Credentials.
 *
 * ## ⛔ Derived from the router, never listed
 *
 * ⛔ **A HAND-KEPT LIST OF ENDPOINTS IS THE ARTEFACT THIS CODEBASE ALREADY HAS
 * SEVERAL COPIES OF.** The phrase *"eight endpoints"* is written by hand in
 * this tree — `grep -rni 'eight endpoints' app/ tests/` is the census, and a
 * list of the sites in this docblock would be the same artefact one level up.
 * **It is correct today**, and a ninth endpoint would leave every one of them
 * wrong, with at least one an assertion string in a test that would go on
 * passing. ⛔ **A LINT OVER THOSE COUNTS WAS WEIGHED AND REFUSED**: the same
 * grep, widened to any spelled number before *endpoints*, returns legitimate
 * **subset** counts across unrelated subjects — IndexNow's seven, Socialite's
 * two, the password-reset door's two — and no regex separates *"two of the
 * eight"* from *"the two that fetch"*. **A check tuned until it stops crying
 * wolf is one tuned until it catches nothing** (511). So the population here
 * comes from {@see Route::getRoutes()} on every call, the count is never
 * written down, and the prose counts are left where they are.
 *
 * ⚠️ **THE MAPPING IS REFLECTION AND NOT A GREP** (10203). A route names a
 * controller; PHP resolves that controller's own parameter types; `instanceof`
 * answers whether one of them declares {@see VerifiesWebhookSenders}. There is
 * no needle, so the case-sensitivity failure a textual census has — narrowing
 * silently rather than failing loudly — is structurally absent rather than
 * guarded against.
 *
 * ## ⛔ It reports state and never claims a fault, and that is deliberate
 *
 * ⛔ **AN ENDPOINT WHOSE VENDOR NOBODY HAS CONNECTED IS NOT A FAULT**, and
 * {@see WebhookVerification} is right that counting one would *"ring
 * a bell on every fresh install about a vendor that is working"* (511). This
 * class therefore has **no threshold, no bell and no verdict**: it prints what
 * is set and what is not, per endpoint, and a person reads it. ⚠️ **A bell would
 * need an *is this vendor in use* predicate, and no honest one exists for all
 * three material shapes** — see {@see ShowWebhookMaterial}
 * for what was weighed and why it was refused rather than guessed at.
 *
 * ⛔ **AND IT COUNTS NOTHING.** {@see CredentialStore::holds()} answers off the
 * shared classifier rather than through `resolve()`, precisely so that running
 * this on every deployment does not ring the credential bell by looking — which
 * is {@see CredentialStore::board()}'s own rule.
 */
final class WebhookMaterialCensus
{
    /**
     * The prefix every webhook route this platform answers sits under.
     *
     * ⚠️ **THE PREFIX IS THE POPULATION AND `bootstrap/app.php` ALREADY DEPENDS
     * ON THAT.** Its CSRF exemption list names every `webhooks/` route
     * individually and refuses a `webhooks/*` wildcard, and
     * `tests/Feature/Architecture/MessagingTest.php` fails the build on a route
     * under this prefix that is not named there. So *"under `webhooks/`"* is
     * already a load-bearing boundary rather than a naming habit.
     *
     * ⚠️ **`POST /mail/unsubscribe/{token}` IS OUTSIDE IT AND CORRECTLY SO.** It
     * verifies a signed token with `APP_KEY`, which is present on any install
     * that boots at all, so there is no at-rest question to ask about it.
     */
    public const string PREFIX = 'webhooks/';

    public function __construct(private readonly CredentialStore $credentials) {}

    /**
     * Every webhook endpoint that reaches a declaring verifier, with what it
     * holds and what it does not.
     *
     * ⚠️ **ONE ROW PER ROUTE, NOT PER SECRET.** Three Infobip endpoints share
     * one key and each gets a row, because what an operator is deciding about is
     * an endpoint: two of the three carry delivery receipts and telephony
     * events, and the third decides whether STOP is heard.
     *
     * @return list<WebhookMaterialLine>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->routes() as $uri => $verifiers) {
            foreach ($verifiers as $verifier) {
                $present = [];
                $missing = [];

                $material = $verifier::verifyingMaterial();

                foreach ($material->credentials as $key) {
                    $this->holdsCredential($key) ? $present[] = $key : $missing[] = $key;
                }

                foreach ($material->configuration as $path) {
                    self::isConfigured($path) ? $present[] = $path : $missing[] = $path;
                }

                $lines[] = new WebhookMaterialLine(
                    $uri,
                    class_basename($verifier),
                    $present,
                    $missing,
                );
            }
        }

        usort($lines, static fn (WebhookMaterialLine $a, WebhookMaterialLine $b): int => [$a->uri, $a->verifier] <=> [$b->uri, $b->verifier]);

        return $lines;
    }

    /**
     * Every declaring verifier a webhook route in this application actually
     * injects — the router's half of the coverage question.
     *
     * ⛔ **IT EXISTS BECAUSE THE LINT WAS COMPUTING IT ITSELF AND HAD THE OLD
     * PAIR — 12180.** `tests/Feature/Architecture/CredentialsTest.php`'s *"every
     * webhook verifier in the tree is reachable from a webhook route"* held an
     * inline re-implementation of {@see self::declarersFor()} over a fixed
     * `['__construct', '__invoke']`, which is what this class read until 12085.
     * **Both copies were correct on the day the second was written**, and
     * `CLAUDE.md` §*What a chokepoint lint owes* says exactly that is 8460's
     * shape: *a lint holding its own copy of the pattern its guard reads*.
     *
     * ⛔ **THE DIVERGENCE WAS MEASURED IN BOTH DIRECTIONS BEFORE THIS WAS
     * WRITTEN, AND THE LOUD ONE IS A FALSE ACCUSATION.** Driven with a planted
     * `App\` verifier typed on a named action method and a route registered
     * `[Controller::class, 'handle']`: the copy reached **nothing**, so the
     * declarer landed in its own `array_diff()` and the lint reddened saying
     * *"no webhook route reaches it"* about an endpoint that injects it on every
     * request. The quiet direction — a verifier declared on `__invoke` at a
     * route dispatching some other method — the copy reported as **reached**,
     * and only the sibling arm's {@see self::undeclared()} caught it.
     *
     * ⚠️ **THE TWO SIDES OF THAT COVERAGE GUARD STAY INDEPENDENTLY DERIVED**,
     * which is the thing a shared accessor could have broken. The declarer side
     * is a filesystem walk of `app/` in the test and never passes through
     * {@see self::PREFIX}; only this side asks the router. So narrowing the
     * prefix still reddens rather than moving both sides together — and
     * {@see self::strays()} is the arm that names it.
     *
     * ⚠️ **A FLOOR AND NOT A CEILING, exactly as {@see self::declarersFor()}
     * is** — a verifier a controller pulls out of the container by hand is
     * injected by nothing this can see, and is what {@see self::undeclared()}
     * is for.
     *
     * @return list<class-string<VerifiesWebhookSenders>>
     */
    public function declarers(): array
    {
        $declarers = [];

        foreach ($this->routes() as $verifiers) {
            foreach ($verifiers as $verifier) {
                if (! in_array($verifier, $declarers, true)) {
                    $declarers[] = $verifier;
                }
            }
        }

        sort($declarers);

        return $declarers;
    }

    /**
     * The webhook routes whose controller reaches no declaring verifier at all.
     *
     * ⛔ **THIS IS THE COMPLETENESS HALF AND IT IS THE HALF THAT ROTS.** A census
     * that asserts its rows are non-empty has not asserted its rows are
     * **every** row: a ninth endpoint verifying inline, or with a class that
     * never declared, would simply not appear above and everything would read
     * healthy. ⚠️ **The reassuring direction, again** — so the population and the
     * covered set are compared, and the difference is returned rather than
     * dropped.
     *
     * @return list<string>
     */
    public function undeclared(): array
    {
        $uris = array_keys(array_filter($this->routes(), static fn (array $verifiers): bool => $verifiers === []));

        // `sort()` reindexes, so this is already a list.
        sort($uris);

        return $uris;
    }

    /**
     * Routes that reach a declaring verifier and sit **outside**
     * {@see self::PREFIX}, so the census never looked at them.
     *
     * ⛔ **THE GUARD ON THE PREFIX, AND WITHOUT IT NARROWING THE PREFIX IS
     * INVISIBLE.** Every other answer here is derived through
     * {@see self::PREFIX}, so a test comparing *the routes under the prefix*
     * with *the routes the census covered* moves both sides together and can
     * never fail — 8460's shape, arrived at from the other direction. This one
     * asks the whole router and uses the prefix only to say what it left out.
     *
     * ⚠️ **AND IT IS A REAL FINDING RATHER THAN ONLY AN INSTRUMENT.** A webhook
     * endpoint mounted outside `webhooks/` is also outside `bootstrap/app.php`'s
     * individually-named CSRF exemption list, which `MessagingTest` pins and
     * which deliberately refuses a `webhooks/*` wildcard. **A verifier reachable
     * from somewhere else is two problems, not one.**
     *
     * @return list<string>
     */
    public function strays(): array
    {
        $strays = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), self::PREFIX)) {
                continue;
            }

            if (self::declarersFor($route) !== []) {
                $strays[] = $route->uri();
            }
        }

        $strays = array_values(array_unique($strays));

        sort($strays);

        return $strays;
    }

    /**
     * Every route under {@see self::PREFIX}, mapped to the declaring verifiers
     * its controller asks for.
     *
     * ⚠️ **WHICH METHODS ARE READ IS {@see self::declarersFor()}'s ARGUMENT**,
     * and it is derived from the route rather than fixed.
     *
     * @return array<string, list<class-string<VerifiesWebhookSenders>>>
     */
    private function routes(): array
    {
        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), self::PREFIX)) {
                continue;
            }

            $found[$route->uri()] = self::declarersFor($route);
        }

        ksort($found);

        return $found;
    }

    /**
     * The declaring verifiers a route's controller actually gets injected.
     *
     * ⛔ **THIS READ `['__construct', '__invoke']` AND LARAVEL HAS A THIRD
     * INJECTION POINT — 12085.** The sentence here was *"CONSTRUCTOR **AND**
     * `__invoke`, BECAUSE BOTH ARE INJECTION POINTS"*, which reads as an
     * exhaustive enumeration and is not one:
     * {@see ControllerDispatcher::dispatch()} calls
     * `resolveClassMethodDependencies()` against **whatever method the route
     * names**, so `Route::post('…', [SomeController::class, 'handle'])` gets its
     * verifier resolved exactly the same way. **The docblock's own argument
     * applied verbatim to the spelling it did not read.**
     *
     * ⚠️ **IT WAS LATENT AND THE CONSEQUENCES RAN BOTH WAYS.** All eight
     * registrations are single-action `__invoke` today, so nothing was wrong in
     * the report — but a webhook under the prefix on a named-method controller
     * would have read as **undeclared**, which is a false accusation an operator
     * goes and chases, and one **outside** the prefix would have been invisible
     * to {@see self::strays()}: the exact defect that method exists for,
     * silently.
     *
     * ✅ **THE PAIR IS NOW THE CONSTRUCTOR AND THE ROUTE'S OWN ACTION METHOD**,
     * which is strictly more correct in the other direction too. A controller
     * that declares a verifier on `__invoke` and is mounted at a *different*
     * method never has that parameter resolved, and the old pair would have
     * reported a verifier nothing injects.
     *
     * ⚠️ **AND IT IS STILL A FLOOR RATHER THAN A CEILING.** A verifier a
     * controller resolves out of the container by hand, or takes through a
     * parent's constructor it does not redeclare, is not a typed parameter of
     * either method and does not appear here — which is what
     * {@see self::undeclared()} is for.
     *
     * @return list<class-string<VerifiesWebhookSenders>>
     */
    private static function declarersFor(RoutingRoute $route): array
    {
        $controller = ltrim((string) $route->getControllerClass(), '\\');

        if ($controller === '' || ! class_exists($controller)) {
            return [];
        }

        $reflection = new ReflectionClass($controller);

        $declarers = [];

        foreach (array_unique(['__construct', self::actionMethodOf($route)]) as $method) {
            if (! $reflection->hasMethod($method)) {
                continue;
            }

            foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $name = $type->getName();

                if (! class_exists($name) || ! is_subclass_of($name, VerifiesWebhookSenders::class)) {
                    continue;
                }

                /** @var class-string<VerifiesWebhookSenders> $name */
                if (! in_array($name, $declarers, true)) {
                    $declarers[] = $name;
                }
            }
        }

        return $declarers;
    }

    /**
     * The method the router will actually call on this route's controller.
     *
     * ⛔ **`Route::getActionMethod()` IS THE OBVIOUS PUBLIC API AND IT RETURNS
     * THE CLASS NAME FOR EVERY ENDPOINT IN THIS TREE — MEASURED, NOT READ**
     * (12086). It reads `$action['controller']`, and
     * {@see Router::convertToControllerAction()} sets that
     * key **before** {@see RouteAction::parse()} appends
     * `@__invoke` — so for `Route::post('…', SomeController::class)`, which is
     * how all eight webhook routes are registered, `getActionMethod()` answers
     * `SomeController` rather than `__invoke`.
     *
     * ⚠️ **THE FAILURE IS SILENT IN THE DIRECTION THIS FILE IS ABOUT.** Driven
     * with `getActionMethod()` in place, every one of the eight real endpoints
     * reported as **undeclared** while three freshly written probes — all
     * registered as `[Controller::class, 'method']`, the one spelling that key
     * is correct for — went green. **A plant set drawn from the new shape cannot
     * see the hole, because it does not live in the old one.**
     *
     * ✅ **`$action['uses']` IS WHAT THE DISPATCHER PARSES**
     * ({@see RoutingRoute::getControllerMethod()}, which is
     * `protected`), and it is the same string
     * {@see RoutingRoute::getControllerClass()} above already reads
     * the class out of — so both halves of this answer come from one field.
     */
    private static function actionMethodOf(RoutingRoute $route): string
    {
        $uses = $route->getAction('uses');

        if (! is_string($uses)) {
            return '__invoke';
        }

        $method = Str::parseCallback($uses, '__invoke')[1];

        return is_string($method) ? $method : '__invoke';
    }

    /**
     * ⚠️ **AN UNDECLARED KEY IS *MISSING* HERE RATHER THAN AN EXCEPTION, AND
     * THE LINT IS WHERE IT IS LOUD.** {@see CredentialStore::holds()} refuses a
     * key {@see CredentialManifest} does not name, which is correct — but this
     * class is reached from a deploy step, and R25 says a report may not be a
     * brake. `tests/Feature/Architecture/CredentialsTest.php` fails the build on
     * exactly that state, so it cannot reach a deployment silently.
     */
    private function holdsCredential(string $key): bool
    {
        try {
            return $this->credentials->holds($key);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Whether a configuration path resolves to something an operator supplied.
     *
     * ⚠️ **A STRING AND AN ARRAY, BECAUSE THE TWO CONFIGURED GATES ARE ONE OF
     * EACH.** `platform_mail.sns.topic_arns` is an allowlist that fails closed
     * when empty; the Gmail push audience and service account are strings that
     * fail closed when blank. Treating an empty array as set would report the
     * SES endpoint as configured in precisely the state where it refuses every
     * bounce AWS publishes.
     */
    private static function isConfigured(string $path): bool
    {
        $value = config($path);

        if (is_array($value)) {
            return $value !== [];
        }

        return is_string($value) && trim($value) !== '';
    }
}
