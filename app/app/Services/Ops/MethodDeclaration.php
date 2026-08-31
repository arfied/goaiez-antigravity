<?php

declare(strict_types=1);

namespace App\Services\Ops;

/**
 * One public method declaration, and what the tree spells about it -
 * decisions 9160-9163.
 *
 * ⛔ **THE SUBJECT IS A DECLARATION SITE AND NOT A CLASS'S SURFACE, AND THE
 * TWO DIFFER BY ABOUT A THIRD.** A trait method serves every class that uses
 * it, and PHP reflection reports it as declared on each of them: the same tree
 * answers 3,099 declaration sites and 5,055 class-and-method pairs. **A
 * declaration is what an editor deletes**, so it is what a census that exists
 * to be acted on should count; {@see MethodCallers} says so on every run,
 * because a figure without its quantity is the failure that instrument was
 * built to end rather than to join.
 *
 * ⚠️ **`appCalls` IS AN ATTRIBUTED COUNT AND `weak` IS DELIBERATELY NOT ONE.**
 * A quoted string or a property read that happens to spell this method's name
 * is evidence about a *name*, never about a *receiver*, so it is carried
 * beside the verdict rather than folded into it - except for the three roles
 * where naming is how the framework dispatches, which
 * {@see MethodCallers::NAME_DISPATCHED} enumerates.
 */
final readonly class MethodDeclaration
{
    /**
     * @param  string  $class  the declaring class, interface, trait or enum
     * @param  string  $file  relative to the project root
     * @param  string  $role  what invokes this class - see `MethodCallers::roleOf()`
     * @param  list<string>  $tokens  the names a call site would spell: the
     *                                method name, plus the scope name for an
     *                                Eloquent scope, whose declared name never
     *                                appears at any call site
     * @param  string|null  $framework  why the framework calls this rather than
     *                                  `app/`, or null if nothing excuses it
     * @param  string|null  $entryPoint  `routed` or `listener` where the router
     *                                   or the event map names this method - a
     *                                   call site with a receiver, and the
     *                                   strongest evidence this census has
     * @param  string|null  $acknowledgement  the `@uncalled` reason written at
     *                                        the declaration, or null
     * @param  int  $appCalls  attributed call sites in `app/`, outside this file
     * @param  int  $ownFileCalls  attributed call sites inside this file
     * @param  int  $edgeCalls  occurrences in `routes/`, `config/`, `resources/`
     * @param  int  $testCalls  call-shaped occurrences in `tests/`
     * @param  int  $weak  property reads and quoted identifiers spelling one
     *                     of `$tokens`, anywhere in the corpus - unattributed,
     *                     and counted as a call for the three roles the
     *                     framework dispatches by name
     * @param  bool  $inComment  whether an `app/` comment names it
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $file,
        public int $line,
        public string $role,
        public array $tokens,
        public ?string $framework,
        public ?string $entryPoint,
        public ?string $acknowledgement,
        public int $appCalls,
        public int $ownFileCalls,
        public int $edgeCalls,
        public int $testCalls,
        public int $weak,
        public bool $inComment,
    ) {}

    /**
     * Whether anything this census counts as a caller calls it.
     *
     * ⚠️ **A CALL FROM ITS OWN FILE COUNTS**, which is why `self::state()`
     * reports it separately instead. A method whose only caller is its own
     * class is over-wide visibility rather than a dead method, and merging the
     * two would put forty-odd `private`-should-have-been methods into a list
     * whose whole value is that every row on it is worth a look.
     *
     * ⚠️ **A CALL FROM `tests/` DOES NOT COUNT.** The question this instrument
     * answers is `CLAUDE.md`'s - *which of our own public methods have no
     * caller in `app/`* - and a tested method with no application caller is the
     * exact shape it was asked for. The count is carried on the row.
     */
    public function isCalled(): bool
    {
        return $this->appCalls > 0 || $this->ownFileCalls > 0 || $this->edgeCalls > 0;
    }

    /**
     * Nothing calls it, nothing excuses it, and nobody has written down why.
     */
    public function isDead(): bool
    {
        return $this->framework === null
            && $this->acknowledgement === null
            && ! $this->isCalled();
    }

    /**
     * An `@uncalled` note that has stopped being true.
     *
     * ⛔ **THIS IS WHAT KEEPS THE ACKNOWLEDGEMENT FROM BECOMING AN EXEMPTION
     * LIST.** `CLAUDE.md`'s deferred-with-a-reason bullet asks what tells a
     * deliberate absence from a defect, and answers it: *"whether the absence
     * is pinned by a test that reddens when it stops being true"*. A note
     * saying nothing calls this is a claim, and the day somebody calls it the
     * claim is wrong - so the census reports it and a lint fails the build on
     * it. **An exemption that cannot go stale is one nobody will ever revisit.**
     */
    public function acknowledgementIsStale(): bool
    {
        return $this->acknowledgement !== null && $this->isCalled();
    }

    /**
     * What the tree holds about this method, in one word.
     *
     * `called` · `self` (its own file only) · `edge` (a template, a route file
     * or a config map only) · `tested` (only `tests/`) · `documented` (only a
     * comment) · `silent` (nothing anywhere).
     *
     * ⛔ **`documented` IS THE FINDING AND NOT A FOOTNOTE**, on
     * {@see ColumnReaders}' rule one level up: a method whose only trace is a
     * paragraph recording that nothing calls it **reads as alive to every grep
     * anybody will ever run**, so the ones somebody already investigated are
     * precisely the ones a hand census drops.
     */
    public function state(): string
    {
        if ($this->appCalls > 0 || $this->edgeCalls > 0) {
            return $this->appCalls > 0 ? 'called' : 'edge';
        }

        if ($this->ownFileCalls > 0) {
            return 'self';
        }

        if ($this->testCalls > 0) {
            return 'tested';
        }

        return $this->inComment ? 'documented' : 'silent';
    }

    public function reference(): string
    {
        return $this->class.'::'.$this->method;
    }
}
