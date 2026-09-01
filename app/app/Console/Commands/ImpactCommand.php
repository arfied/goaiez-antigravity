<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\Manifest;
use App\Doctor\ManifestReader;
use Illuminate\Console\Command;

/**
 * `impact <module|event|table>` — what breaks if this changes.
 *
 * ⭐⭐⭐ N-263-02 IS THE WHOLE DESIGN OF THIS COMMAND:
 *
 *     "impact is exhaustive or it REFUSES — a partial impact answer reads as
 *      'nothing else is affected'."
 *
 * ⛔⛔ That is the sharpest requirement in §263, and it inverts the normal
 * instinct for a tool like this. A search command that finds 3 of 5 callers is
 * mildly useful. An IMPACT command that finds 3 of 5 is WORSE THAN NOTHING,
 * because the person reads the two it missed as "safe to change" and ships.
 *
 * So every path here either proves it saw the whole graph, or it refuses and
 * says which part it could not see. There is no partial answer.
 *
 * ⭐ This command only became possible on 2026-08-27, when P-208's dangerous
 * direction reached ZERO. Until every consumed event had a declared emitter,
 * "who reacts to this?" had no complete answer — the graph had 37 holes in it,
 * and impact would have been confidently wrong 37 different ways.
 */
final class ImpactCommand extends Command
{
    protected $signature = 'impact {subject : a module id, an event token, or a table name}';

    protected $description = 'Blast radius. Exhaustive, or it refuses — a partial answer reads as "nothing else is affected".';

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $subject = (string) $this->argument('subject');
        $all = $this->manifests->all();

        if ($all === []) {
            $this->error('REFUSED — no module manifests found.');
            $this->line('  Run `php artisan module:scaffold` first. With no manifests every');
            $this->line('  answer would be "nothing is affected", which is a lie, not an answer.');

            return self::FAILURE;
        }

        // ⛔ THE COMPLETENESS GATE. If any module cannot be read, the graph has a
        //    hole, and a blast radius computed over a holed graph is exactly the
        //    false reassurance N-263-02 forbids.
        if (($blind = $this->blindSpots($all)) !== []) {
            $this->error('REFUSED — the graph is incomplete, so no impact answer can be exhaustive.');
            foreach ($blind as $b) {
                $this->line("  ⛔ {$b}");
            }
            $this->newLine();
            $this->line('  A partial impact answer reads as "nothing else is affected" (N-263-02).');
            $this->line('  Fix the gaps above, then re-run.');

            return self::FAILURE;
        }

        return match (true) {
            (bool) preg_match('/^(X|C)-[A-Za-z0-9]+$/', $subject) => $this->module($subject, $all),
            str_contains($subject, '.') => $this->event($subject, $all),
            default => $this->tableImpact($subject, $all),
        };
    }

    /**
     * @param  list<Manifest>  $all
     * @return list<string>
     */
    private function blindSpots(array $all): array
    {
        $out = [];
        $emitted = [];
        $consumed = [];

        foreach ($all as $m) {
            if ($m->provides === [] && $m->emits === [] && $m->consumes === []) {
                $out[] = "{$m->id} declares nothing — it cannot be reasoned about";
            }
            foreach ($m->emits as $e) {
                $emitted[$e] = true;
            }
            foreach ($m->consumes as $e) {
                $consumed[$e][] = $m->id;
            }
        }

        // ⭐ A consumed event with no emitter is a hole UNLESS it is declared
        //   @ingress (enters from outside — a browser, a vendor, a human) or
        //   @scheduled (a clock fires it). §235 classified these deliberately:
        //   "these are NOT ghosts. No module emits them because no module SHOULD."
        foreach ($consumed as $event => $consumers) {
            if (isset($emitted[$event])) {
                continue;
            }
            if ($this->isDeclaredExternal($event, $all)) {
                continue;
            }
            $c = implode(', ', $consumers);
            $out[] = "{$event} is consumed by {$c} and emitted by nobody — the graph cannot be walked through it";
        }

        return $out;
    }

    /** @param list<Manifest> $all */
    private function isDeclaredExternal(string $event, array $all): bool
    {
        foreach ($all as $m) {
            $src = $this->manifests->source($m);
            if (preg_match('/@(ingress|scheduled)\s+'.preg_quote($event, '/').'\b/', $src) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Manifest> $all */
    private function module(string $id, array $all): int
    {
        $me = null;
        foreach ($all as $m) {
            if ($m->id === $id) {
                $me = $m;
            }
        }

        if ($me === null) {
            $this->error("REFUSED — {$id} has no manifest.");

            return self::FAILURE;
        }

        $this->line("# IMPACT — {$id}");
        $this->newLine();

        // ① Who listens to what it emits. These break if an event is renamed or dropped.
        $downstream = [];
        foreach ($me->emits as $e) {
            foreach ($all as $m) {
                if ($m->id !== $id && in_array($e, $m->consumes, true)) {
                    $downstream[$e][] = $m->id;
                }
            }
        }

        $this->line('## BREAKS IF YOU CHANGE AN EVENT IT EMITS');
        if ($downstream === []) {
            // ⭐ Not a hole. P-208 is DIRECTIONAL: an emitted event with no
            //   subscriber is a recorded fact, and 277 of them are correct.
            $this->line('  nothing subscribes. Its emissions are recorded facts, not wires.');
        }
        foreach ($downstream as $event => $mods) {
            $this->line("  {$event}  →  ".implode(' · ', $mods));
        }

        // ② What it depends on. These break IT.
        $this->newLine();
        $this->line('## BREAKS IT IF THEY CHANGE');
        foreach ($me->consumes as $e) {
            $emitters = [];
            foreach ($all as $m) {
                if (in_array($e, $m->emits, true)) {
                    $emitters[] = $m->id;
                }
            }
            $src = $emitters === [] ? 'EXTERNAL (@ingress or @scheduled)' : implode(' · ', $emitters);
            $this->line("  {$e}  ←  {$src}");
        }
        if ($me->consumes === []) {
            $this->line('  nothing — it declares @consumes none.');
        }

        // ③ Table readers. A schema change reaches further than an event change
        //    and is far easier to under-estimate.
        $this->newLine();
        $this->line('## READS ITS TABLES (a schema change reaches these)');
        $readers = [];
        foreach ($me->ownsTable as $t) {
            foreach ($all as $m) {
                if ($m->id !== $id && in_array($t, $m->readsTable, true)) {
                    $readers[$t][] = $m->id;
                }
            }
        }
        foreach ($me->ownsTable as $t) {
            $r = $readers[$t] ?? [];
            $this->line("  {$t}  →  ".($r === [] ? 'no declared readers' : implode(' · ', $r)));
        }

        $this->newLine();
        $total = count($downstream, COUNT_RECURSIVE) + count($me->consumes) + count($readers, COUNT_RECURSIVE);
        $this->line("EXHAUSTIVE over {$this->countModules($all)} modules. {$total} declared connections.");
        $this->line('⛔ Declared connections only. An undeclared import is invisible here — that is');
        $this->line('   what doctor\'s boundary stage exists to prevent, and why it runs first.');

        return self::SUCCESS;
    }

    /** @param list<Manifest> $all */
    private function event(string $event, array $all): int
    {
        $emitters = [];
        $consumers = [];
        foreach ($all as $m) {
            if (in_array($event, $m->emits, true)) {
                $emitters[] = $m->id;
            }
            if (in_array($event, $m->consumes, true)) {
                $consumers[] = $m->id;
            }
        }

        $this->line("# IMPACT — {$event}");
        $this->newLine();
        $this->line('  emitted by   : '.($emitters === [] ? 'EXTERNAL (@ingress / @scheduled)' : implode(' · ', $emitters)));
        $this->line('  consumed by  : '.($consumers === [] ? 'nobody — a recorded fact' : implode(' · ', $consumers)));
        $this->newLine();

        if ($consumers !== []) {
            $this->line('  ⛔ Renaming this breaks '.count($consumers).' module(s) SILENTLY.');
            $this->line('     A consumer waiting on a name nobody emits does not error — it');
            $this->line('     simply never runs. §235 found ten of these; one was a single letter');
            $this->line('     (action.invoke vs action.invoked) and it made two modules deaf.');
        }

        return self::SUCCESS;
    }

    /**
     * ⛔⛔⛔ NAMED `tableImpact`, NOT `table` — AND THIS WAS A FATAL ERROR.
     *
     * `Illuminate\Console\Command` already defines a PUBLIC `table()` for
     * rendering console tables. Declaring `private function table()` here made
     * PHP refuse to load the class at all:
     *
     *   "Access level to ImpactCommand::table() must be public
     *    (as in class Illuminate\Console\Command)"
     *
     * ⭐ And it did not fail quietly in this one command — it killed
     *   `package:discover`, so EVERY artisan command on the whole tree died,
     *   including `composer install`. One private method took the platform down.
     *
     * @param list<Manifest> $all
     */
    private function tableImpact(string $table, array $all): int
    {
        $owner = null;
        $readers = [];
        foreach ($all as $m) {
            if (in_array($table, $m->ownsTable, true)) {
                $owner = $m->id;
            }
            if (in_array($table, $m->readsTable, true)) {
                $readers[] = $m->id;
            }
        }

        $this->line("# IMPACT — {$table}");
        $this->newLine();
        $this->line('  owned by  : '.($owner ?? '⛔ NOBODY — an unowned table is a P-163 violation'));
        $this->line('  read by   : '.($readers === [] ? 'no declared readers' : implode(' · ', $readers)));

        if ($readers !== []) {
            $this->newLine();
            $this->line('  ⛔ §259: a SWITCH and a CONTRACT may not ship in one deploy.');
            $this->line('     With '.count($readers).' reader(s), add the new shape first, verify equality');
            $this->line('     for a full business cycle, switch the read, and only then contract.');
        }

        return self::SUCCESS;
    }

    /** @param list<Manifest> $all */
    private function countModules(array $all): int
    {
        return count($all);
    }
}
