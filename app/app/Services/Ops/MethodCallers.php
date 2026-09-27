<?php

declare(strict_types=1);

namespace App\Services\Ops;

use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Component;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;
use Throwable;

/**
 * Every public method this application declares, and whether anything in
 * `app/` calls it - decisions 9160-9163.
 *
 * ## ⛔ THE INVERSE OF `db:column-readers`, AND IT ERRS THE OTHER WAY
 *
 * `docs/FAILURE-SHAPES.md`: *"A writerless column is a decoration; **a readerless one is a
 * claim nobody checks**, and it is invisible to a column census, which measures
 * whether a *name* is spelled and never whether a *method* is called."* That
 * bullet ends by recommending this instrument and recording that it was not
 * built. This is it.
 *
 * ⛔ **AND THE ERROR DIRECTION IS REVERSED, WHICH IS THE FIRST THING TO KNOW
 * ABOUT IT.** `ColumnReaders` argues in writing that every residual blind spot
 * of its scores a dead column **alive**, *"a false dead gets investigated and
 * corrected, a false alive is never looked at again"*. **A method census cannot
 * have that property.** Its loudest shapes - an Eloquent scope whose declared
 * name appears at no call site, a relation read as a property, a queued job's
 * `handle()` reached by seventy-five `dispatch()` sites that never spell it -
 * are false **dead**. So the failure available here is 511's: a report that
 * cries wolf is one nobody opens twice.
 *
 * ⚠️ **SO THE FALSE DEADS ARE SPENT ON, NOT APOLOGISED FOR.** Three mechanisms
 * carry that cost, and none of them is a list of method names:
 *
 *  - **The roles are derived from the router, the Gate, the event dispatcher,
 *    the middleware stack and PHP's own type system**, never from a path or a
 *    naming pattern - `CLAUDE.md` 8660-8662. `self::roles()` resolves 123
 *    router actions, 16 policies, 3 listeners and 9 middleware on this tree,
 *    and the lint beside this class asserts each of those counts against the
 *    directory it should equal, so a class the container cannot see reddens the
 *    build instead of quietly scoring dead.
 *  - **A routed action and a mapped listener are CALL SITES, not exemptions.**
 *    The router names the method; that is a caller with a receiver, and it is
 *    stronger evidence than any grep. Only the hooks the framework reaches by
 *    convention rather than by configuration need `self::FRAMEWORK_INVOKED`,
 *    and that map is keyed by **(role, method)** - never by method name alone,
 *    because `handle` is declared on two live inbound service paths in this
 *    tree that are nothing to do with a queue.
 *  - **An Eloquent scope is matched under the name a call site spells.**
 *    `scopeBillable` is called as `->billable()`; the declared name appears
 *    nowhere. That is a name transform rather than an excusal, so a scope
 *    nothing uses is still reported.
 *
 * ## ⚠️ WHAT IT COUNTS, AND THE QUANTITY IS PART OF THE ANSWER
 *
 * **Declaration sites.** A trait method is one declaration serving many
 * classes, and PHP reports it as declared on each of them: this tree holds
 * 3,099 declaration sites and 5,055 class-and-method pairs. A declaration is
 * what an editor deletes, so it is what a census meant to be acted on counts -
 * and `ShowMethodCallers` prints the word on every run, because a figure
 * without its quantity is exactly the failure `CLAUDE.md` removed the pixel's
 * byte figure from prose to stop.
 *
 * ## ⚠️ WHAT IT CANNOT SEE
 *
 * The command prints these on every run rather than leaving them here. They
 * split in two, and the split is the point:
 *
 *  - **Toward a false *dead*** - a receiver reached through a variable that
 *    this class cannot type, a method reached by a computed name, a Blade
 *    action written with an interpolated argument. Loud: somebody opens the
 *    row and finds the caller.
 *  - **Toward a false *alive*** - an unattributable call site (`$service->m()`
 *    where `$service` is a property) scores **every** declaration of that name
 *    alive, which is `ColumnReaders`' bare-token collision with a receiver
 *    instead of a table. Quiet, and therefore counted rather than described:
 *    `self::collisions()` is the same pigeonhole arithmetic one level up, and
 *    `--suspect` orders the alive set by fewest calls.
 *
 * ## ⚠️ IT REFUSES NOTHING AND IS NOT A LINT
 *
 * A build-failing *"every public method has a caller"* over a tree with 197
 * enums, sixteen policies and a Contracts directory would ship with an
 * exemption list of hundreds of entries on the day it was written, which is 511
 * within a week. What is machine-checked instead is narrow and is in
 * `tests/Feature/Ops/MethodCensusTest.php`: the derived role counts, that no
 * excusal in the map below is unearned, that no `@uncalled` note has gone
 * stale, and that this census cannot change its own answer by printing it.
 *
 * ⛔ **`ShowMethodCallers` AND `ColumnReaders` ARE NAMED BARE HERE AND MUST NOT
 * BE TIDIED INTO `{@see}` REFERENCES** (8573): `composer lint` hoists a
 * namespaced docblock name into a real `use` statement, and here that would be
 * a Service importing a Console Command - the dependency pointing backwards,
 * generated by a formatter, out of a sentence.
 */
final class MethodCallers
{
    /**
     * Hooks the framework reaches by convention, keyed by **(role, method)**.
     *
     * ⛔ **KEYED BY ROLE AND NOT BY NAME, AND THAT IS THE WHOLE GUARD.**
     * `docs/FAILURE-SHAPES.md`'s 9020-9039 block: *"an exemption keyed by FILE cannot be
     * guarded honestly … the key must name the column"*. Here the key must name
     * the role as well as the method, because these names are ordinary English
     * and are declared on ordinary services: `handle` outside a queue, a
     * command, a controller, a listener or a provider is declared three times
     * in this tree, and two of the three are live inbound paths - a Zernio
     * webhook and an inbound text. A name-keyed list would excuse both.
     *
     * ⛔ **EVERY ENTRY MUST BE EARNED TODAY.** `MethodCensusTest` fails the
     * build on an entry that matches no declaration, because an excusal that
     * covers nothing is the exemption `CLAUDE.md` says cannot go stale - there
     * is nothing to notice. **Add a row when a declaration needs it, never in
     * advance**, and expect that to feel wrong: the first draft of this map was
     * written from Laravel's conventions and **thirty-six of its fifty-three
     * entries covered nothing in this tree** - a mailable verb where no class
     * extends `Mailable`, a policy `before()` none of the sixteen declares,
     * `casts()` where the framework's own signature is protected. Every one of
     * them would have read as considered. ⚠️ **The lint costs nothing when a
     * new hook arrives** - an unlisted one shows up as a false dead, and the
     * failure is only ever the *last* user of an entry being deleted.
     *
     * ⚠️ **A ROUTED CONTROLLER ACTION AND A MAPPED LISTENER ARE ABSENT ON
     * PURPOSE** - they are resolved as call sites in `self::derivedCallers()`,
     * with a receiver, which is stronger than anything this map can say.
     *
     * @var array<string, list<string>>
     */
    public const array FRAMEWORK_INVOKED = [
        'command' => ['handle'],
        'event' => ['broadcastOn', 'broadcastAs', 'broadcastWith'],
        'job' => ['handle', 'failed', 'backoff', 'uniqueId', 'tags'],
        'livewire' => ['mount', 'render'],
        'middleware' => ['handle', 'terminate'],
        'model' => ['getRouteKeyName'],
        'notification' => ['via', 'toMail', 'toArray'],
        'provider' => ['register', 'boot'],
        'request' => ['rules', 'authorize', 'messages', 'withValidator', 'after'],
        'rule' => ['validate'],
    ];

    /**
     * Livewire's prefixed hooks, which are a convention rather than a name.
     *
     * `updatedFooBar()` fires when `$fooBar` changes and nothing spells the
     * method. Guarded exactly as the map above is: an unearned prefix reddens -
     * which is why `updating`, `hydrate` and `dehydrate` are absent, all three
     * having been written here from Livewire's documentation and matching
     * nothing at all.
     *
     * @var list<string>
     */
    public const array LIVEWIRE_HOOK_PREFIXES = ['updated'];

    /**
     * The roles whose framework dispatches by spelling a name in a string.
     *
     * ⛔ **THIS IS AN EVIDENCE RULE AND NOT AN EXCUSAL, WHICH IS WHY IT IS
     * WORTH THE PRECISION IT COSTS.** A policy ability is reached as
     * `authorize('publish', …)` and a relation as `with('locations')` - so for
     * these three, a quoted string or a property read spelling the name is the
     * call site, and a declaration nothing spells anywhere is still reported
     * dead. **The alternative was excusing all sixteen policies and all
     * seventy-five relations outright**, which would have removed 140-odd
     * declarations from the subject in exchange for a sentence.
     *
     * @var list<string>
     */
    public const array NAME_DISPATCHED = ['policy', 'relation', 'accessor'];

    /**
     * Absolute paths kept out of the corpus - see `self::excluding()`.
     *
     * @var list<string>
     */
    private array $excluded = [];

    /** @var list<MethodDeclaration>|null */
    private ?array $lines = null;

    /**
     * Attributed call sites: method name => declaration key => counts.
     *
     * @var array<string, array<string, array{external: int, own: int}>>
     */
    private array $attributed = [];

    /** @var array<string, int> call sites whose receiver this class cannot type */
    private array $loose = [];

    /** @var array<string, int> property reads and quoted identifiers in `app/` */
    private array $weak = [];

    /** @var array<string, int> call-shaped occurrences in `routes/`, `config/`, `resources/` */
    private array $edge = [];

    /** @var array<string, int> quoted identifiers and property reads in the same three */
    private array $edgeWeak = [];

    /** @var array<string, int> call-shaped occurrences in `tests/` */
    private array $tests = [];

    /** @var array<string, int> every identifier inside an `app/` comment */
    private array $comments = [];

    /** @var array<string, true> names handed to `method_exists()` and friends */
    private array $dynamic = [];

    /** @var array<string, string> absolute file => class-like it declares */
    private ?array $classes = null;

    /** @var array<string, string>|null declaration key => role */
    private ?array $derived = null;

    /** @var array<string, string>|null "Class::method" => why the framework calls it */
    private ?array $callers = null;

    /** @var array<string, string>|null declaration key => `routed` or `listener` */
    private ?array $entryPoints = null;

    private bool $scanned = false;

    /**
     * Every public declaration in `app/`, with what calls it.
     *
     * Ordered by file then line, which is the order a reader of the class meets
     * them in.
     *
     * @return list<MethodDeclaration>
     */
    public function all(): array
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $this->scan();

        $lines = [];

        foreach ($this->declarations() as $key => $method) {
            $lines[] = $this->line($key, $method);
        }

        return $this->lines = $lines;
    }

    /**
     * The declarations nothing calls, nothing excuses and nobody has explained.
     *
     * @return list<MethodDeclaration>
     */
    public function dead(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (MethodDeclaration $line): bool => $line->isDead(),
        ));
    }

    /**
     * The `@uncalled` notes that have stopped being true.
     *
     * @return list<MethodDeclaration>
     */
    public function stale(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (MethodDeclaration $line): bool => $line->acknowledgementIsStale(),
        ));
    }

    /**
     * Every `@uncalled` note in the tree, stale or not.
     *
     * @return list<MethodDeclaration>
     */
    public function acknowledged(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (MethodDeclaration $line): bool => $line->acknowledgement !== null,
        ));
    }

    /**
     * How many call sites in `app/` spell `$name`, attributed or not.
     *
     * ⚠️ **A `-1` IS NOT A ZERO ANYWHERE IN THIS CLASS** - see
     * `ColumnReaders::codeMatches()` for the same distinction. There is no
     * regex here that can fail to run, so the value never occurs; the method
     * exists to order `--suspect`, where a wrong zero would put a live method
     * at the top of a lead list and waste the reader's first look.
     */
    public function callMatches(string $name): int
    {
        $this->scan();

        $total = $this->loose[$name] ?? 0;

        foreach ($this->attributed[$name] ?? [] as $counts) {
            $total += $counts['external'] + $counts['own'];
        }

        return $total;
    }

    /**
     * Method names carried by more declarations than the tree has
     * unattributable call sites for.
     *
     * ⛔ **THE SAME PIGEONHOLE `ColumnReaders::collisions()` RUNS, WITH A
     * RECEIVER WHERE IT HAS A TABLE.** A call site whose receiver this class
     * cannot type - `$this->texter->send(…)`, where the property's type is
     * whatever the constructor was handed - is **one** site and reaches at most
     * **one** declaration, but it scores **every** declaration of that name
     * alive. So a name carried by `D` declarations that have no attributed call
     * of their own, with `L` unattributable sites, leaves at least `D - L` of
     * them with no caller at all - and when `L >= 1` every one of them is
     * missing from the dead list.
     *
     * ⚠️ **THIS IS THE ONLY QUIET BLIND SPOT WITH AN ARM RATHER THAN A
     * PARAGRAPH**, which is why it is here: the class docblock concedes the
     * false-alive direction, and a fourth conceding paragraph would be
     * 314-316's shape - the sentence explaining the limit is what stops the
     * next reader looking for an instance.
     *
     * ⚠️ **FRAMEWORK-EXCUSED AND ACKNOWLEDGED DECLARATIONS ARE OUT OF THE
     * COUNT**, on `ColumnReaders`' rule for framework tables: they are not on
     * the dead list to begin with, so counting them would manufacture a
     * shortfall out of an excusal rather than find one.
     *
     * @return list<array{method: string, declarations: list<string>, sites: int, unaccounted: int, scoredAlive: bool}>
     */
    public function collisions(): array
    {
        $byName = [];

        foreach ($this->all() as $line) {
            if ($line->framework !== null || $line->acknowledgement !== null) {
                continue;
            }

            // ⚠️ ONLY THE DECLARATIONS ALIVE *SOLELY* ON UNATTRIBUTED EVIDENCE
            // ARE AT RISK. One with a call of its own is accounted for, and
            // counting it would overstate the shortfall - so the attributed
            // index is read directly here rather than the row's arithmetic,
            // which has the loose count already folded into it.
            if ($this->evidenceOtherThanUntyped($line) > 0) {
                continue;
            }

            $byName[$line->method][] = $line->reference();
        }

        $collisions = [];

        foreach ($byName as $method => $references) {
            // ⚠️ **A NAME ONE DECLARATION CARRIES IS NOT A COLLISION AND ITS
            // ROW WOULD BE THE DEAD LIST REPEATED.** `ColumnReaders` skips a
            // column fewer than two tables carry for the same reason; without
            // it this report was mostly single-declaration rows already printed
            // above, which is a report nobody reads to the end.
            if (count($references) < 2) {
                continue;
            }

            $sites = $this->loose[$method] ?? 0;

            if (count($references) <= $sites) {
                continue;
            }

            sort($references);

            $collisions[] = [
                'method' => (string) $method,
                'declarations' => $references,
                'sites' => $sites,
                'unaccounted' => count($references) - $sites,
                // ⛔ THE HALF THAT IS NEW INFORMATION. At zero sites every one
                // of them is already on the dead list; at one or more they are
                // all scored alive, so the shortfall is declarations the list
                // OMITS.
                'scoredAlive' => $sites > 0,
            ];
        }

        usort(
            $collisions,
            static fn (array $a, array $b): int => [$b['unaccounted'], $a['method']]
                <=> [$a['unaccounted'], $b['method']],
        );

        return $collisions;
    }

    /**
     * Everything scoring this declaration alive **except** the untyped sites.
     *
     * ⛔ **EVERY OTHER KIND OF EVIDENCE HAS TO COME OUT OF THE PIGEONHOLE OR IT
     * COUNTS THE WRONG DECLARATIONS, AND IT DID.** The first draft subtracted
     * only the attributed sites, and `--collisions` reported all twenty-three
     * `automationKey()` implementations as unaccounted for when every one of
     * them is alive through the abstract parent, and all fifteen `viewAny()`
     * policy abilities when every one is reached by its own name in a string.
     * The question the arithmetic needs is narrow: **is this declaration alive
     * on nothing but a call whose receiver could not be typed?**
     */
    private function evidenceOtherThanUntyped(MethodDeclaration $line): int
    {
        $loose = 0;

        foreach ($line->tokens as $token) {
            $loose += $this->loose[$token] ?? 0;
        }

        return ($line->appCalls - $loose) + $line->ownFileCalls + $line->edgeCalls;
    }

    /**
     * The same census over the same corpus with `$paths` taken out of it.
     *
     * ⛔ **THIS EXISTS BECAUSE THE CENSUS MEASURES ITS OWN SOURCE, AND ITS OWN
     * SOURCE IS WHERE SOMEBODY WRITES ABOUT METHODS** - `ColumnReaders` has the
     * identical seam for the identical reason (8785, 8925) and its test
     * measured the identical hazard: two names planted in one printed line
     * silently removed a row from the report built to find them.
     *
     * ⛔ **AND THIS INSTRUMENT INHERITS IT WORSE.** A column name can hide
     * behind a `table.` qualifier and `ColumnReaders::pattern()`'s lookbehind
     * excludes the dot. **A method name has no such convention**: a quoted
     * identifier in a printed line is the same token as the one at a call site,
     * and there is nothing to put in front of it. So the discipline that saves
     * that class - write the qualifier - has no equivalent here, and the effect
     * lint is the only thing between a caveat and a deleted finding.
     *
     * ⚠️ **`$paths` ARE ABSOLUTE FILE PATHS, COMPARED EXACTLY**, so a path that
     * matches nothing removes nothing and says so to nobody. A caller that
     * needs to know the exclusion happened has to prove it, which is why the
     * test asserts a token only these files carry has gone to zero.
     *
     * @uncalled 9162 - a seam for the effect lint below and for nothing else,
     *   which is the shape this whole instrument cannot tell from a defect. It
     *   is the first subject of the stale-note arm, and it is written here
     *   rather than argued in a review because a ruling nobody wrote down is
     *   one the next census raises again.
     *
     * @param  list<string>  $paths
     */
    public function excluding(array $paths): self
    {
        $census = new self;
        $census->excluded = $paths;

        return $census;
    }

    /**
     * The directories a caller may live in.
     *
     * ⚠️ **`app/Models` IS PRESENT, WHERE THE COLUMN CENSUS EXCLUDES IT.** That
     * exclusion is about `$casts` and `$fillable` - *"neither a cast nor a
     * factory is a call site"* - and a model calling a service is a call site
     * like any other.
     *
     * ⚠️ **`tests/` IS ABSENT FROM THIS LIST AND SCANNED SEPARATELY.** The
     * question is *which of our own public methods have no caller in `app/`*,
     * so a test cannot answer it - but *tested and never called* is the most
     * informative row this report prints, so the count is carried beside the
     * verdict instead of being folded into it. `database/` is absent outright:
     * a factory is not a call site.
     *
     * @return list<string>
     */
    public function scannedPaths(): array
    {
        return [
            base_path('app'),
            base_path('resources'),
            base_path('routes'),
            base_path('config'),
        ];
    }

    /**
     * What invokes each class, derived rather than inferred from its path.
     *
     * ⛔ **EVERY ONE OF THESE COMES FROM THE ROUTER, THE GATE, THE EVENT
     * DISPATCHER, THE MIDDLEWARE STACK OR PHP'S TYPE SYSTEM** - `CLAUDE.md`
     * 8660-8662, *"derive a lint's subject set from the router, the schema or
     * the container and assert the count, never from a pattern over source text
     * alone"*. A `App\Policies\` path prefix would have been two lines and
     * would answer for a class the Gate cannot actually resolve; asking the
     * Gate answers for the ones it can, and the lint asserts the two agree.
     *
     * ⚠️ **ORDER IS LOAD-BEARING WHERE THE TESTS OVERLAP** - a notification and
     * a mailable both implement `ShouldQueue` in this tree, so the queue test
     * runs last of the three or every notification becomes a job.
     *
     * @return array<string, string> class => role
     */
    public function roles(): array
    {
        if ($this->derived !== null) {
            return $this->derived;
        }

        $routed = [];
        $listeners = [];

        foreach ($this->derivedCallers() as $reference => $reason) {
            $class = substr($reference, 0, (int) strpos($reference, '::'));

            if ($reason === 'routed') {
                $routed[$class] = true;
            }

            if ($reason === 'listener') {
                $listeners[$class] = true;
            }
        }

        $policies = array_flip($this->policyClasses());
        $middleware = array_flip($this->middlewareClasses());

        $roles = [];

        foreach ($this->classLikes() as $class) {
            $roles[$class] = $this->roleOf($class, $routed, $listeners, $policies, $middleware);
        }

        return $this->derived = $roles;
    }

    /**
     * The policy classes the Gate resolves for this application's models.
     *
     * ⚠️ **ASKED OF THE GATE, ONE MODEL AT A TIME, BECAUSE `Gate::policies()`
     * ANSWERS EMPTY HERE** - nothing registers a policy explicitly, so the map
     * is Laravel's own convention resolution and only `getPolicyFor()` runs it.
     *
     * @return list<string>
     */
    public function policyClasses(): array
    {
        $policies = [];

        foreach ($this->classLikes() as $class) {
            $reflection = $this->reflect($class);

            if ($reflection === null || ! $reflection->isSubclassOf(Model::class) || $reflection->isAbstract()) {
                continue;
            }

            $policy = Gate::getPolicyFor($class);

            if ($policy !== null) {
                $policies[is_object($policy) ? $policy::class : (string) $policy] = true;
            }
        }

        return array_values(array_filter(
            array_keys($policies),
            static fn (string $class): bool => str_starts_with($class, 'App\\'),
        ));
    }

    /**
     * The middleware classes this application's HTTP stack actually runs.
     *
     * Aliases, every route's gathered stack, and the kernel's own global and
     * grouped lists - which is where a middleware with no alias and no route
     * lives.
     *
     * @return list<string>
     */
    public function middlewareClasses(): array
    {
        $aliases = Route::getMiddleware();
        $classes = [];

        foreach ($aliases as $target) {
            if (is_string($target) && str_starts_with($target, 'App\\')) {
                $classes[$target] = true;
            }
        }

        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $entry) {
                if (! is_string($entry)) {
                    continue;
                }

                $name = explode(':', $entry)[0];
                $resolved = $aliases[$name] ?? $name;

                if (is_string($resolved) && str_starts_with($resolved, 'App\\')) {
                    $classes[$resolved] = true;
                }
            }
        }

        $kernel = app(HttpKernel::class);
        $reflection = new ReflectionClass($kernel);

        foreach (['middleware', 'middlewareGroups'] as $property) {
            if (! $reflection->hasProperty($property)) {
                continue;
            }

            $value = $reflection->getProperty($property)->getValue($kernel);

            if (! is_array($value)) {
                continue;
            }

            array_walk_recursive($value, static function (mixed $entry) use (&$classes): void {
                if (is_string($entry) && str_starts_with($entry, 'App\\')) {
                    $classes[$entry] = true;
                }
            });
        }

        return array_keys($classes);
    }

    /**
     * The methods something outside `app/` names as an entry point, and what
     * names them.
     *
     * ⛔ **THESE ARE CALL SITES AND NOT EXCUSALS, AND THE DIFFERENCE MATTERS.**
     * A route says *this class, this method*; so does the event map. That is a
     * caller with a receiver, which is stronger evidence than any occurrence of
     * a bare token anywhere in the tree - and it means a controller action
     * nobody routed is still reported, where a role-wide exemption for
     * controllers would have hidden it.
     *
     * @return array<string, string> "Class::method" => `routed` or `listener`
     */
    public function derivedCallers(): array
    {
        if ($this->callers !== null) {
            return $this->callers;
        }

        $callers = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getAction();
            $uses = $action['uses'] ?? null;

            if (is_string($uses) && str_contains($uses, '@')) {
                [$class, $method] = explode('@', $uses, 2);
            } elseif (is_string($uses) && class_exists($uses)) {
                [$class, $method] = [$uses, '__invoke'];
            } else {
                continue;
            }

            if (str_starts_with($class, 'App\\')) {
                $callers[$class.'::'.$method] = 'routed';
            }
        }

        foreach (Event::getRawListeners() as $listeners) {
            foreach ((array) $listeners as $listener) {
                if (! is_string($listener) || ! str_starts_with($listener, 'App\\')) {
                    continue;
                }

                $callers[str_contains($listener, '@') ? str_replace('@', '::', $listener) : $listener.'::handle'] = 'listener';
            }
        }

        return $this->callers = $callers;
    }

    /**
     * The declarations the router and the event map name, keyed the same way
     * a call site is.
     *
     * ⛔ **RESOLVED THROUGH `self::declarationKey()` RATHER THAN MATCHED ON THE
     * SPELLED CLASS, BECAUSE A ROUTE NAMES A CLASS AND NOT A DECLARATION.** A
     * controller action inherited from an `App\` parent is routed as the child
     * and declared on the parent; matching the string would credit neither and
     * leave the parent on the dead list. ⚠️ **The two counts are printed side
     * by side on every run** and they differ: an entry naming a method that
     * resolves nowhere in `app/` is a component the router names by class, or a
     * verb declared inside the framework, and it credits nothing here.
     *
     * @return array<string, string>
     */
    public function entryPoints(): array
    {
        if ($this->entryPoints !== null) {
            return $this->entryPoints;
        }

        $keys = [];

        foreach ($this->derivedCallers() as $reference => $reason) {
            [$class, $method] = explode('::', $reference, 2);
            $key = $this->declarationKey($class, $method);

            if ($key !== null) {
                $keys[$key] = $reason;
            }
        }

        return $this->entryPoints = $keys;
    }

    /**
     * Every class, interface, trait and enum `app/` declares.
     *
     * ⚠️ **PSR-4 RATHER THAN A PARSE.** `composer.json` maps `App\` to `app/`,
     * so the path is the name; `self::reflect()` is what proves it, and a file
     * whose class cannot be resolved is counted and reported rather than
     * skipped in silence.
     *
     * @return list<string>
     */
    public function classLikes(): array
    {
        return array_values($this->classesByFile());
    }

    /**
     * Files under `app/` whose class-like nothing could resolve.
     *
     * ⚠️ **PRINTED ON EVERY RUN.** A file this returns is a file whose
     * declarations are missing from the census entirely, which is the one error
     * that is invisible in both directions.
     *
     * @return list<string>
     */
    public function unresolvable(): array
    {
        $unresolvable = [];

        foreach ($this->phpFilesIn(base_path('app')) as $file) {
            if (! isset($this->classesByFile()[$file])) {
                $unresolvable[] = $this->relative($file);
            }
        }

        sort($unresolvable);

        return $unresolvable;
    }

    /**
     * The declaration sites, keyed `relative/path.php:line`.
     *
     * ⛔ **A TRAIT METHOD IS ONE DECLARATION, NOT ONE PER USING CLASS.** PHP
     * reports a trait's method as declared on every class that uses it -
     * `getDeclaringClass()` returns the class - so the file check is what
     * separates 3,099 declaration sites from 5,055 class-and-method pairs. A
     * declaration is what an editor deletes.
     *
     * ⚠️ **`__construct` IS OUT**: it is called by the container on every
     * resolution and by `new` at every site, and nothing about it answers the
     * question. Every other magic method stays in the subject and is excused
     * with its reason named, so the excusal is visible in the report.
     *
     * @return array<string, ReflectionMethod>
     */
    public function declarations(): array
    {
        $declarations = [];

        foreach ($this->classesByFile() as $file => $class) {
            $reflection = $this->reflect($class);

            if ($reflection === null) {
                continue;
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                if ($method->getFileName() !== $file || $method->getName() === '__construct') {
                    continue;
                }

                $declarations[$this->relative($file).':'.$method->getStartLine()] = $method;
            }
        }

        ksort($declarations);

        return $declarations;
    }

    /**
     * One declaration, scored.
     */
    private function line(string $key, ReflectionMethod $method): MethodDeclaration
    {
        $class = $method->getDeclaringClass()->getName();
        $name = $method->getName();
        $role = $this->roleOfDeclaration($class, $method);
        $tokens = $this->tokensFor($role, $name);
        $file = substr($key, 0, (int) strrpos($key, ':'));

        $external = 0;
        $own = 0;
        $edge = 0;
        $tests = 0;
        $weak = 0;
        $weakEdge = 0;
        $inComment = false;

        foreach ($tokens as $token) {
            $external += $this->loose[$token] ?? 0;
            $external += $this->attributed[$token][$key]['external'] ?? 0;
            $own += $this->attributed[$token][$key]['own'] ?? 0;

            // ⛔ AN IMPLEMENTATION INHERITS THE CALL SITES OF THE DECLARATION
            // IT SATISFIES - see `self::inheritedFrom()`.
            foreach ($this->inheritedFrom($class, $name) as $inherited) {
                $counts = $this->attributed[$token][$inherited] ?? ['external' => 0, 'own' => 0];
                $external += $counts['external'] + $counts['own'];
            }

            $edge += $this->edge[$token] ?? 0;
            $tests += $this->tests[$token] ?? 0;
            $weak += $this->weak[$token] ?? 0;
            $weakEdge += $this->edgeWeak[$token] ?? 0;
            $inComment = $inComment || isset($this->comments[$token]);
        }

        // ⛔ THE ONE PLACE WEAK EVIDENCE BECOMES A VERDICT, AND IT IS KEYED BY
        // ROLE. A policy ability is reached by its name in a string and a
        // relation by a property read; for every other role a quoted identifier
        // is a coincidence waiting to score a dead method alive, and it was
        // measured as one before this rule existed: three occurrences of the
        // word in one template's status copy scored the flagship example of
        // `CLAUDE.md`'s own readerless bullet ALIVE, and a URL segment in
        // `routes/web.php` did the same for its sibling.
        $entryPoint = $this->entryPoints()[$key] ?? null;

        if (in_array($role, self::NAME_DISPATCHED, true)) {
            $external += $weak;
            $edge += $weakEdge;
        }

        // ⛔ A ROUTE OR AN EVENT MAP NAMING THIS METHOD IS A CALL SITE WITH A
        // RECEIVER, WHICH IS STRONGER THAN ANY OCCURRENCE OF A BARE TOKEN. It
        // is counted rather than excused, so a controller action nobody routed
        // is still reported - where a role-wide exemption for controllers would
        // have hidden it.
        if ($entryPoint !== null) {
            $external++;
        }

        return new MethodDeclaration(
            class: $class,
            method: $name,
            file: $file,
            line: (int) $method->getStartLine(),
            role: $role,
            tokens: $tokens,
            framework: $this->frameworkReason($class, $method, $role),
            entryPoint: $entryPoint,
            acknowledgement: $this->acknowledgement($method),
            appCalls: $external,
            ownFileCalls: $own,
            edgeCalls: $edge,
            testCalls: $tests,
            weak: $weak + $weakEdge,
            inComment: $inComment,
        );
    }

    /**
     * The names a call site would spell for this declaration.
     *
     * ⛔ **AN ELOQUENT SCOPE'S DECLARED NAME APPEARS AT NO CALL SITE AND THAT
     * IS THE LOUDEST FALSE *DEAD* THIS INSTRUMENT CAN PRODUCE.**
     * `scopeBillable()` is called `->billable()`; nine declarations in this tree
     * have that shape. It is a **name transform and not an excusal**, so a
     * scope nothing uses is still reported - which is the whole difference
     * between spending a false dead and hiding a finding.
     *
     * @return list<string>
     */
    private function tokensFor(string $role, string $name): array
    {
        if ($role !== 'model' && $role !== 'relation' && $role !== 'accessor') {
            return [$name];
        }

        if (! str_starts_with($name, 'scope') || strlen($name) <= 5) {
            return [$name];
        }

        $called = lcfirst(substr($name, 5));

        return $called === $name ? [$name] : [$name, $called];
    }

    /**
     * Why the framework calls this rather than `app/`, or null if nothing does.
     *
     * ⚠️ **PRECEDENCE IS DELIBERATE AND THE NARROWEST REASON WINS**, because
     * the reason is printed and a reader acts on it: a name handed to
     * `method_exists()` is a specific site somebody can open, where *"a
     * Livewire hook"* is a convention they would have to take on trust.
     */
    private function frameworkReason(string $class, ReflectionMethod $method, string $role): ?string
    {
        $name = $method->getName();

        if (isset($this->dynamic[$name])) {
            return 'named to method_exists() or call_user_func()';
        }

        if ($this->overridesFrameworkDeclaration($class, $name)) {
            return 'overrides a declaration outside App\\';
        }

        if (str_starts_with($name, '__') && $name !== '__invoke') {
            return 'a PHP magic method';
        }

        if (in_array($name, self::FRAMEWORK_INVOKED[$role] ?? [], true)) {
            return 'a '.$role.' hook';
        }

        if ($role === 'livewire') {
            foreach (self::LIVEWIRE_HOOK_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix) && strlen($name) > strlen($prefix)) {
                    return 'a livewire '.$prefix.'* hook';
                }
            }
        }

        return null;
    }

    /**
     * Whether a parent class or interface outside `App\` declares this name.
     *
     * ⚠️ **TWENTY-THREE DECLARATIONS IN THIS TREE, WHICH IS WHY THE MAP ABOVE
     * EXISTS AT ALL.** The obvious design - excuse whatever overrides a
     * framework declaration and write no list - covers less than one per cent
     * of the framework-invoked surface, because Laravel's conventions are
     * duck-typed: nothing declares `handle()`, `mount()` or `via()` anywhere
     * for a subclass to override.
     */
    private function overridesFrameworkDeclaration(string $class, string $name): bool
    {
        $reflection = $this->reflect($class);

        if ($reflection === null) {
            return false;
        }

        for ($parent = $reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            if (! str_starts_with($parent->getName(), 'App\\') && $parent->hasMethod($name)) {
                return true;
            }
        }

        foreach ($reflection->getInterfaces() as $interface) {
            if (! str_starts_with($interface->getName(), 'App\\') && $interface->hasMethod($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `@uncalled` reason written at the declaration, or null.
     *
     * ⛔ **AN ACKNOWLEDGEMENT IS A RECORD OF A RULING AND IS NOT EVIDENCE OF A
     * CALL.** `CLAUDE.md` is explicit that prose in code is not evidence, and
     * this reads a docblock - so it is worth being exact about what it buys.
     * It does not make a method called; it moves a row out of the list of
     * things nobody has looked at into a list of things somebody has, **and it
     * is checked**: the day a caller appears the note is wrong, the census says
     * so, and a lint fails the build. A bare `@uncalled` with no reason is
     * refused for the same reason - an exemption with no argument is the thing
     * `CLAUDE.md` says decays, and a reason is what the next reader needs.
     *
     * ⚠️ **THE TAG IS READ FROM THE DOCBLOCK, WHICH IS THE COMMENT CORPUS**, so
     * it cannot contribute a call to anything: `self::scan()` separates
     * comments before any call is counted.
     */
    private function acknowledgement(ReflectionMethod $method): ?string
    {
        $doc = $method->getDocComment();

        if ($doc === false || preg_match('/@uncalled\s+(\S.*?)(?=\n\s*\*\s*(?:@|\/|$))/s', $doc, $found) !== 1) {
            return null;
        }

        $note = preg_replace('/\s*\n\s*\*\s*/', ' ', $found[1]) ?? $found[1];

        return trim(rtrim(trim($note), '*/'));
    }

    /**
     * @param  array<string, mixed>  $routed
     * @param  array<string, mixed>  $listeners
     * @param  array<string, mixed>  $policies
     * @param  array<string, mixed>  $middleware
     */
    private function roleOf(string $class, array $routed, array $listeners, array $policies, array $middleware): string
    {
        $reflection = $this->reflect($class);

        if ($reflection === null) {
            return 'plain';
        }

        if ($reflection->isInterface()) {
            return 'contract';
        }

        if ($reflection->isTrait()) {
            return 'trait';
        }

        if ($reflection->isEnum()) {
            return 'enum';
        }

        $is = static fn (string $parent): bool => class_exists($parent)
            && ($reflection->getName() === $parent || $reflection->isSubclassOf($parent));

        return match (true) {
            $is(Component::class) => 'livewire',
            $is(Command::class) => 'command',
            isset($routed[$class]) || $is(Controller::class) => 'controller',
            isset($listeners[$class]) => 'listener',
            isset($policies[$class]) => 'policy',
            $is(FormRequest::class) => 'request',
            $is(Notification::class) => 'notification',
            $is(Mailable::class) => 'mailable',
            $reflection->implementsInterface(ShouldBroadcast::class) => 'event',
            $reflection->implementsInterface(ShouldQueue::class) => 'job',
            isset($middleware[$class]) => 'middleware',
            $is(ServiceProvider::class) => 'provider',
            $is(Model::class) => 'model',
            $reflection->implementsInterface(ValidationRule::class) => 'rule',
            $reflection->implementsInterface(CastsAttributes::class) => 'cast',
            $reflection->implementsInterface(Throwable::class) => 'exception',
            default => 'plain',
        };
    }

    /**
     * The class's role, narrowed by what this particular method returns.
     *
     * ⛔ **A RELATION AND AN ACCESSOR ARE REACHED AS PROPERTIES AND THE RETURN
     * TYPE IS WHAT SAYS SO** - `->locations` and `->locations(` are both live
     * shapes for one declaration, and seventy-five of them are declared here.
     * Deriving it from the type rather than from the name is what stops a
     * service method called `locations()` being treated the same way.
     */
    private function roleOfDeclaration(string $class, ReflectionMethod $method): string
    {
        $role = $this->roles()[$class] ?? 'plain';

        if ($role !== 'model') {
            return $role;
        }

        $type = $method->getReturnType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return $role;
        }

        $name = $type->getName();

        if ($name === Relation::class || (class_exists($name) && is_subclass_of($name, Relation::class))) {
            return 'relation';
        }

        return $name === Attribute::class ? 'accessor' : $role;
    }

    /**
     * Functions that dispatch by a name written as a string.
     *
     * ⚠️ **THREE OF THE FIVE RETURN NOTHING IN THIS TREE AND THEY STAY.** This
     * is not an excusal list whose entries have to be earned - it is a list of
     * PHP's dynamic-dispatch primitives, and one appearing tomorrow is exactly
     * the event that would otherwise make this census wrong in silence. Today
     * `method_exists()` alone reaches fifty-five `label()` declarations across
     * `app/Enums` from a single line of `Support\Admin\Field`.
     *
     * @var list<string>
     */
    private const array DYNAMIC_DISPATCH = [
        'method_exists',
        'is_callable',
        'call_user_func',
        'call_user_func_array',
        'forward_static_call',
    ];

    /**
     * Read the tree once and index every call site in it.
     *
     * ⚠️ **ONCE, AND INTO A MAP RATHER THAN A STRING.** `ColumnReaders`
     * concatenates the tree and runs one regex per column, which is fifteen
     * hundred passes; there are eighteen hundred distinct method names here and
     * the same design would be a minute of work per run. Every count below is
     * accumulated in a single pass, so the corpus is read once and each name is
     * a lookup.
     *
     * ⚠️ **`.blade.php` IS NOT TOKENISED**, on `ColumnReaders`' argument:
     * `token_get_all()` reads a template as inline HTML and loses the `{{ }}`
     * expressions that are the real references, which would score live methods
     * dead - the loud direction, but wrongly and for hundreds at once. A
     * template is matched for `name(` and for a quoted identifier instead, so a
     * `wire:click="showSign(…)"` counts and the word `send` on a button does
     * not.
     */
    private function scan(): void
    {
        if ($this->scanned) {
            return;
        }

        $this->scanned = true;

        $app = base_path('app').DIRECTORY_SEPARATOR;

        foreach ($this->files() as $file) {
            $inApp = str_starts_with($file, $app);
            $source = (string) file_get_contents($file);

            if (! str_ends_with($file, '.php') || str_ends_with($file, '.blade.php')) {
                $this->indexRaw($source);

                continue;
            }

            $this->indexPhp($file, $source, $inApp);
        }

        $this->indexTests();
    }

    /**
     * Call-shaped occurrences in `tests/`, by name and nothing else.
     *
     * ⚠️ **NOT ATTRIBUTED AND DELIBERATELY SO.** This count never decides a
     * verdict - `tests/` is not a caller - and its only job is to tell a reader
     * which rows are *tested and never called*, which is the shape both of the
     * uncalled methods in the first triaged namespace turned out to have.
     * Attribution would cost a second full resolution pass to sharpen a number
     * nothing branches on.
     */
    private function indexTests(): void
    {
        foreach ($this->phpFilesIn(base_path('tests')) as $file) {
            $comments = '';
            $tokens = $this->significant((string) file_get_contents($file), $comments);

            foreach ($tokens as $i => $token) {
                if ($token[0] !== T_STRING || ! $this->opens($tokens, $i)) {
                    continue;
                }

                $previous = $tokens[$i - 1] ?? null;

                if ($previous !== null && $this->isMemberOperator($previous)) {
                    $this->tests[$token[1]] = ($this->tests[$token[1]] ?? 0) + 1;
                }
            }
        }
    }

    /**
     * Index one PHP file: calls with their receivers, properties, strings.
     */
    private function indexPhp(string $file, string $source, bool $inApp): void
    {
        $comments = '';
        $tokens = $this->significant($source, $comments);
        $class = $this->classesByFile()[$file] ?? null;
        $imports = $this->imports($source);
        $variables = [];

        foreach (preg_split('/[^A-Za-z0-9_]+/', $comments) ?: [] as $word) {
            if ($word !== '') {
                $this->comments[$word] = ($this->comments[$word] ?? 0) + 1;
            }
        }

        foreach ($tokens as $i => $token) {
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $literal = trim($token[1], '"\'');

                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $literal) === 1) {
                    $this->weigh($inApp, $literal);
                }

                continue;
            }

            if ($token[0] === T_FUNCTION) {
                $variables = array_merge($variables, $this->parameterTypes($tokens, $i));

                continue;
            }

            if ($token[0] !== T_STRING) {
                continue;
            }

            $previous = $tokens[$i - 1] ?? null;
            $member = $previous !== null && $this->isMemberOperator($previous);

            if (! $this->opens($tokens, $i)) {
                if ($member) {
                    $this->weigh($inApp, $token[1]);
                }

                continue;
            }

            if (! $member) {
                if (in_array(strtolower($token[1]), self::DYNAMIC_DISPATCH, true)) {
                    $this->collectDynamic($tokens, $i);
                }

                continue;
            }

            if (! $inApp) {
                $this->edge[$token[1]] = ($this->edge[$token[1]] ?? 0) + 1;

                continue;
            }

            $this->attribute($file, $class, $imports, $variables, $tokens, $i, $token[1]);
        }
    }

    /**
     * Attribute one `app/` call site to a declaration, or give up loudly.
     *
     * ⛔ **GIVING UP IS THE QUIET DIRECTION AND IS WHY `self::collisions()`
     * EXISTS.** An unattributable site is added to **every** declaration of
     * that name, so a dead one is scored alive by a call that was never
     * reaching it - `ColumnReaders`' bare-token collision, with a receiver
     * where it has a table. The arithmetic counts them rather than a paragraph
     * describing them.
     *
     * ⚠️ **WHAT IS RESOLVED IS `$this`, `self`, `static`, `parent` AND A
     * SPELLED CLASS NAME**, and nothing else. `$this->texter->send()` is not
     * resolved: the property's type is whatever the constructor was handed, and
     * typing it means resolving the constructor, the container binding and the
     * `match` on a config value that picks the implementation - which is the
     * point at which a census becomes an interpreter. `app(X::class)->m()` is
     * unresolved for the same reason and is the commoner shape of the two.
     *
     * @param  array<int, array{0: int, 1: string}>  $tokens
     * @param  array<string, string>  $imports
     * @param  array<string, string>  $variables
     */
    private function attribute(string $file, ?string $class, array $imports, array $variables, array $tokens, int $index, string $name): void
    {
        $target = $this->receiver($tokens, $index, $class, $imports, $variables, $file);
        $key = $target === null ? null : $this->declarationKey($target, $name);

        if ($key === null) {
            $this->loose[$name] = ($this->loose[$name] ?? 0) + 1;

            return;
        }

        $own = str_starts_with($key, $this->relative($file).':');
        $counts = $this->attributed[$name][$key] ?? ['external' => 0, 'own' => 0];
        $counts[$own ? 'own' : 'external']++;
        $this->attributed[$name][$key] = $counts;
    }

    /**
     * The declarations on ancestors of `$class` that `$class::$name` overrides.
     *
     * ⛔ **WITHOUT THIS THE REPORT IS 511's LINT NOBODY OPENS TWICE, AND IT
     * WAS MEASURED THAT WAY BEFORE IT EXISTED.** `AutopilotJob` declares
     * `automationKey()` abstract and calls it fifteen times from inside itself;
     * twenty-three concrete jobs implement it and **not one call site anywhere
     * spells a concrete class**. The first run of this census reported all
     * twenty-three as dead - a quarter of the whole list, every row wrong, and
     * the identical shape covers `CmsAdapter`'s verbs and every other interface
     * this application dispatches through.
     *
     * ⚠️ **THE COST IS PAID IN THE QUIET DIRECTION AND IS REAL.** An
     * implementation that nothing binds is now scored alive by calls that
     * dispatch to a *different* implementation - which is the scout's shape #1
     * with its sign flipped. The trade is deliberate: a false dead here is not
     * one row a reader dismisses but a systematic quarter of the list, and a
     * list that is a quarter noise is the one nobody opens again. **It is
     * printed as a caveat on every run.**
     *
     * @return list<string>
     */
    private function inheritedFrom(string $class, string $name): array
    {
        $cached = $this->inherited[$class.'::'.$name] ?? null;

        if ($cached !== null) {
            return $cached;
        }

        $reflection = $this->reflect($class);
        $keys = [];

        if ($reflection !== null) {
            $ancestors = [];

            for ($parent = $reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
                $ancestors[] = $parent->getName();
            }

            foreach ($reflection->getInterfaces() as $interface) {
                $ancestors[] = $interface->getName();
            }

            $own = $this->declarationKey($class, $name);

            foreach ($ancestors as $ancestor) {
                $key = $this->declarationKey($ancestor, $name);

                if ($key !== null && $key !== $own) {
                    $keys[$key] = true;
                }
            }
        }

        return $this->inherited[$class.'::'.$name] = array_keys($keys);
    }

    /**
     * The declaration key `$class::$name` resolves to, or null if it is not
     * one of ours.
     */
    private function declarationKey(string $class, string $name): ?string
    {
        $reflection = $this->reflect($class);

        if ($reflection === null || ! $reflection->hasMethod($name)) {
            return null;
        }

        $method = $reflection->getMethod($name);
        $file = $method->getFileName();

        if ($file === false || ! str_starts_with($file, base_path('app').DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $this->relative($file).':'.$method->getStartLine();
    }

    /**
     * The class the call at `$index` reaches, or null where nothing types it.
     *
     * ⛔ **THE TYPE DECLARATIONS ARE READ, AND WITHOUT THEM THIS COMMAND
     * RESURRECTS METHODS BY BEING WRITTEN.** `ShowMethodCallers` calls its own
     * service as `$callers->dead()` - a variable receiver - and on the first
     * run that scored **every** declaration named `dead()`, `stale()` or
     * `collisions()` anywhere in the tree alive, including five that had been
     * on the dead list an hour earlier. **A census that spreads its own call
     * sites over every same-named method in the application is not measuring
     * the application.** So a parameter's declared type, a promoted
     * constructor property's type and `app(X::class)` are resolved.
     *
     * ⚠️ **WHAT IS STILL UNTYPED**: an assignment (`$x = $this->make();`), a
     * union or intersection type, a closure's `use` clause, an array element,
     * and anything reached through a chain longer than one property. Those go
     * to `self::attribute()`'s loose bucket, are counted for every declaration
     * of the name, and are what `self::collisions()` measures.
     *
     * ⚠️ **THE VARIABLE MAP IS PER FILE AND NOT PER FUNCTION**, which is a
     * deliberate trade recorded here rather than hidden: resetting it at each
     * `function` loses a closure body's enclosing parameters, and merging costs
     * a wrong attribution only where one file types the same variable name as
     * two different classes **and** the method exists on the first - at which
     * point `self::declarationKey()` still has to find the method or the site
     * falls back to loose.
     *
     * @param  array<int, array{0: int, 1: string}>  $tokens
     * @param  array<string, string>  $imports
     * @param  array<string, string>  $variables
     */
    private function receiver(array $tokens, int $index, ?string $class, array $imports, array $variables, string $file): ?string
    {
        $receiver = $tokens[$index - 2] ?? null;

        if ($receiver === null) {
            return null;
        }

        if ($receiver[0] === T_VARIABLE) {
            if ($receiver[1] === '$this') {
                return $class;
            }

            $type = $variables[$receiver[1]] ?? null;

            return $type === null ? null : $this->resolve($type, $imports, $file);
        }

        if ($receiver[1] === ')') {
            return $this->constructedReceiver($tokens, $index, $imports, $file);
        }

        if (! in_array($receiver[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            return null;
        }

        // ⚠️ `$this->reader->all()` reaches `reader` here, which is a PROPERTY
        // and not a class name. The operator in front of it is what tells the
        // two apart, and nothing else does - so the property's own declared
        // type is what resolves it, and an untyped one stays unresolved.
        $before = $tokens[$index - 3] ?? null;

        if ($before !== null && $this->isMemberOperator($before)) {
            return $this->propertyType($tokens, $index, $class, $imports, $file);
        }

        return match (strtolower($receiver[1])) {
            'self', 'static' => $class,
            'parent' => ($parent = $class === null ? null : ($this->reflect($class)?->getParentClass() ?: null))
                === null ? null : $parent->getName(),
            default => $this->resolve($receiver[1], $imports, $file),
        };
    }

    /**
     * The class behind `$this->property->method()`, from the property's type.
     *
     * @param  array<int, array{0: int, 1: string}>  $tokens
     * @param  array<string, string>  $imports
     */
    private function propertyType(array $tokens, int $index, ?string $class, array $imports, string $file): ?string
    {
        $holder = $tokens[$index - 4] ?? null;

        if ($class === null || $holder === null || $holder[0] !== T_VARIABLE || $holder[1] !== '$this') {
            return null;
        }

        $reflection = $this->reflect($class);
        $property = $tokens[$index - 2][1];

        if ($reflection === null || ! $reflection->hasProperty($property)) {
            return null;
        }

        $type = $reflection->getProperty($property)->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        unset($imports, $file);

        return $type->getName();
    }

    /**
     * The class behind `app(X::class)->method()` and `(new X)->method()`.
     *
     * ⚠️ **TWO SHAPES AND NO MORE.** `app(X::class)` is the commonest typed
     * receiver in this tree that no declaration types, and both patterns are
     * exact token sequences rather than a pattern over source - a chain longer
     * than this is `self::collisions()`' business.
     *
     * @param  array<int, array{0: int, 1: string}>  $tokens
     * @param  array<string, string>  $imports
     */
    private function constructedReceiver(array $tokens, int $index, array $imports, string $file): ?string
    {
        $spelled = null;

        if (($tokens[$index - 3][1] ?? '') === 'class'
            && ($tokens[$index - 4][0] ?? 0) === T_DOUBLE_COLON
            && ($tokens[$index - 6][1] ?? '') === '('
            && ($tokens[$index - 7][1] ?? '') === 'app') {
            $spelled = $tokens[$index - 5][1] ?? null;
        }

        if (($tokens[$index - 4][0] ?? 0) === T_NEW) {
            $spelled = $tokens[$index - 3][1] ?? null;
        }

        return $spelled === null ? null : $this->resolve($spelled, $imports, $file);
    }

    /**
     * The typed parameters of the function whose keyword sits at `$index`.
     *
     * ⚠️ **A UNION OR INTERSECTION IS DROPPED RATHER THAN GUESSED.** Two
     * candidate receivers is not a receiver, and picking the first would
     * attribute a call to a declaration it may never reach - which is a false
     * *alive* on one and leaves a false *dead* on the other, the only shape in
     * this class that is wrong in both directions at once.
     *
     * @param  array<int, array{0: int, 1: string}>  $tokens
     * @return array<string, string>
     */
    private function parameterTypes(array $tokens, int $index): array
    {
        $count = count($tokens);
        $start = null;

        for ($i = $index + 1; $i < $count && $i < $index + 4; $i++) {
            if ($tokens[$i][1] === '(') {
                $start = $i;

                break;
            }
        }

        if ($start === null) {
            return [];
        }

        $types = [];
        $depth = 0;
        $spelled = null;
        $ambiguous = false;

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token[1] === '(') {
                $depth++;

                continue;
            }

            if ($token[1] === ')') {
                $depth--;

                if ($depth === 0) {
                    return $types;
                }

                continue;
            }

            if ($token[1] === ',') {
                $spelled = null;
                $ambiguous = false;

                continue;
            }

            if ($token[1] === '|' || $token[1] === '&') {
                $ambiguous = true;

                continue;
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $spelled ??= $token[1];

                continue;
            }

            if ($token[0] === T_VARIABLE) {
                if ($spelled !== null && ! $ambiguous) {
                    $types[$token[1]] = $spelled;
                }

                $spelled = null;
                $ambiguous = false;
            }
        }

        return $types;
    }

    /**
     * Resolve a spelled class name against the file's imports and namespace.
     *
     * @param  array<string, string>  $imports
     */
    private function resolve(string $name, array $imports, string $file): ?string
    {
        if (str_starts_with($name, '\\')) {
            return class_exists($trimmed = ltrim($name, '\\')) ? $trimmed : null;
        }

        $head = explode('\\', $name)[0];

        if (isset($imports[$head])) {
            $resolved = $imports[$head].substr($name, strlen($head));

            return class_exists($resolved) ? $resolved : null;
        }

        $class = $this->classesByFile()[$file] ?? null;

        if ($class !== null && str_contains($class, '\\')) {
            $candidate = substr($class, 0, (int) strrpos($class, '\\')).'\\'.$name;

            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return class_exists($name) ? $name : null;
    }

    /**
     * The file's `use` statements, alias => fully qualified name.
     *
     * ⚠️ **READ WITH A REGEX OVER THE RAW SOURCE**, which is the one place in
     * this class a pattern is allowed to stand in for the tokeniser: a `use`
     * statement is anchored to the start of a line by PSR-12 and by
     * `composer lint`, and a grouped or brace-nested import - which this rules
     * out - appears nowhere in this tree.
     *
     * @return array<string, string>
     */
    private function imports(string $source): array
    {
        preg_match_all(
            '/^use\s+(?:function\s+)?([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/m',
            $source,
            $found,
            PREG_SET_ORDER,
        );

        $imports = [];

        foreach ($found as $match) {
            $fqn = $match[1];
            $alias = ($match[2] ?? '') !== '' ? $match[2] : substr($fqn, (int) strrpos('\\'.$fqn, '\\'));
            $imports[$alias] = $fqn;
        }

        return $imports;
    }

    /**
     * Every identifier handed as a string to the dispatch call at `$index`.
     *
     * @param  array<int, array{0: int, 1: string}>  $tokens
     */
    private function collectDynamic(array $tokens, int $index): void
    {
        $depth = 0;

        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token[1] === '(') {
                $depth++;
            } elseif ($token[1] === ')') {
                $depth--;

                if ($depth === 0) {
                    return;
                }
            } elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $literal = trim($token[1], '"\'');

                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $literal) === 1) {
                    $this->dynamic[$literal] = true;
                }
            }
        }
    }

    /**
     * A template, a stylesheet or a script - and the three shapes it holds.
     *
     * ⛔ **A QUOTED IDENTIFIER IN A TEMPLATE IS WEAK EVIDENCE AND MEASURING IT
     * IS WHAT PROVED IT.** Before this split, the retired direct Google Business client's availability check
     * - the flagship instance of `CLAUDE.md`'s own readerless bullet, verified
     * uncalled by two scouts - was scored **alive** by three occurrences of the
     * word in one support template's status copy, and its sibling
     * `accounts()` by a URL segment in `routes/web.php`. **The instrument would
     * have missed both of the examples it was built for.**
     *
     * ⚠️ **A LIVEWIRE ACTION IS THE REASON THE THIRD PATTERN EXISTS.**
     * `wire:click="save"` carries no parentheses, so demoting quoted
     * identifiers on its own would have scored every argument-free Livewire
     * action dead - hundreds at once, which is 511's report nobody opens twice.
     * `wire:model` and `wire:key` name a property rather than a method and are
     * excluded; `wire:target` names a method and is kept.
     */
    private function indexRaw(string $source): void
    {
        $strong = [
            '/([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
            '/wire:(?!model|key)[A-Za-z0-9_.:-]+\s*=\s*["\'][\s$]*([A-Za-z_][A-Za-z0-9_]*)/',
        ];

        foreach ($strong as $pattern) {
            preg_match_all($pattern, $source, $found);

            foreach ($found[1] as $name) {
                $this->edge[$name] = ($this->edge[$name] ?? 0) + 1;
            }
        }

        preg_match_all('/[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]/', $source, $found);

        foreach ($found[1] as $name) {
            $this->edgeWeak[$name] = ($this->edgeWeak[$name] ?? 0) + 1;
        }
    }

    /**
     * Record a quoted identifier or a property read - never a call.
     */
    private function weigh(bool $inApp, string $name): void
    {
        if ($inApp) {
            $this->weak[$name] = ($this->weak[$name] ?? 0) + 1;

            return;
        }

        $this->edgeWeak[$name] = ($this->edgeWeak[$name] ?? 0) + 1;
    }

    /**
     * The file's tokens with whitespace dropped and comments split off.
     *
     * @return array<int, array{0: int, 1: string}>
     */
    private function significant(string $source, string &$comments): array
    {
        $significant = [];

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                $significant[] = [0, $token];

                continue;
            }

            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $comments .= "\n".$token[1];

                continue;
            }

            if ($token[0] === T_WHITESPACE) {
                continue;
            }

            $significant[] = [$token[0], $token[1]];
        }

        return $significant;
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $tokens
     */
    private function opens(array $tokens, int $index): bool
    {
        return ($tokens[$index + 1][1] ?? '') === '(';
    }

    /**
     * @param  array{0: int, 1: string}  $token
     */
    private function isMemberOperator(array $token): bool
    {
        return in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
    }

    /**
     * @return array<string, string> absolute file => class-like name
     */
    private function classesByFile(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $classes = [];
        $root = base_path('app').DIRECTORY_SEPARATOR;

        foreach ($this->phpFilesIn(base_path('app')) as $file) {
            $relative = substr($file, strlen($root));
            $class = 'App\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

            if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
                $classes[$file] = $class;
            }
        }

        return $this->classes = $classes;
    }

    /** @var array<string, ReflectionClass<object>|null> */
    private array $reflections = [];

    /** @var array<string, list<string>> */
    private array $inherited = [];

    /**
     * @return ReflectionClass<object>|null
     */
    private function reflect(string $class): ?ReflectionClass
    {
        if (array_key_exists($class, $this->reflections)) {
            return $this->reflections[$class];
        }

        // ⚠️ **THE FOUR `*_exists()` CALLS ARE THE GUARD AND THE `try` THAT
        // USED TO SIT HERE WAS DEAD CODE.** `ReflectionClass` throws only on a
        // name that resolves to nothing, and a name that resolves to nothing
        // cannot reach this line - so catching it read as care and caught
        // nothing. `self::unresolvable()` is where a file with no class-like
        // is reported, and it is reported rather than swallowed.
        if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
            return $this->reflections[$class] = null;
        }

        return $this->reflections[$class] = new ReflectionClass($class);
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = [];

        foreach ($this->scannedPaths() as $path) {
            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<SplFileInfo> $found */
            $found = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($found as $entry) {
                if ($entry->isFile() && preg_match('/\.(php|js|css)$/', $entry->getFilename()) === 1) {
                    $files[] = $entry->getPathname();
                }
            }
        }

        // ⚠️ FILTERED HERE RATHER THAN IN `scan()`, SO THE EXCLUSION REACHES
        // THE COMMENT HALF TOO - `ColumnReaders::files()` carries the same note
        // for the same reason.
        if ($this->excluded !== []) {
            $files = array_values(array_filter(
                $files,
                fn (string $file): bool => ! in_array($file, $this->excluded, true),
            ));
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        /** @var iterable<SplFileInfo> $found */
        $found = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($found as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.php')) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $file): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
    }
}
