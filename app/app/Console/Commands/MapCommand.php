<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\ManifestReader;
use Illuminate\Console\Command;

/**
 * `map` — the whole system, on one screen, read from the code.
 *
 * ⭐⭐⭐ N-263-03: "map is GENERATED ON EVERY RUN — a stale map is the roster
 * problem again."
 *
 * ⛔⛔ That sentence is load-bearing, and this programme earned it the hard way.
 * The roster problem was: the plan held 122 modules while both trackers said
 * 119, and NOTHING NOTICED, because a count written down once is a claim, not a
 * measurement. Three fully-specified modules were invisible to every tool.
 *
 * So `map` writes nothing to disk and caches nothing. It cannot be committed,
 * cannot be edited by hand, cannot be quoted from memory, and cannot disagree
 * with the code — because it IS the code, rendered.
 *
 * ⭐ Every number below is counted at the moment you run it.
 */
final class MapCommand extends Command
{
    protected $signature = 'map
        {--events : show the event graph instead of the module list}
        {--tables : show table ownership instead}';

    protected $description = 'The system as the code declares it. Generated every run — never stored, never stale.';

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = $this->manifests->all();

        if ($all === []) {
            $this->error('No manifests. Run `php artisan module:scaffold` first.');
            $this->line('  ⛔ An empty map is not "a small system" — it is a missing one, and the');
            $this->line('     difference matters enough to say out loud.');

            return self::FAILURE;
        }

        if ($this->option('events')) {
            return $this->events($all);
        }

        if ($this->option('tables')) {
            return $this->tables($all);
        }

        return $this->modules($all);
    }

    /** @param list<\App\Doctor\Manifest> $all */
    private function modules(array $all): int
    {
        $byIntent = [];
        foreach ($all as $m) {
            $byIntent[$m->intent ?: 'UNDECLARED'][] = $m;
        }
        ksort($byIntent);

        $this->line('# MAP — '.count($all).' modules, counted just now');
        $this->newLine();

        foreach ($byIntent as $intent => $mods) {
            usort($mods, static fn ($a, $b): int => strcmp($a->id, $b->id));
            $this->line("## {$intent}  (".count($mods).')');

            foreach ($mods as $m) {
                $ships = $m->shipsAt !== '' ? $m->shipsAt : '?';
                $tables = count($m->ownsTable);
                $this->line(sprintf(
                    '  %-9s ships %-4s  provides %-2d  emits %-2d  consumes %-2d  owns %d table%s',
                    $m->id,
                    $ships,
                    count($m->provides),
                    count($m->emits),
                    count($m->consumes),
                    $tables,
                    $tables === 1 ? '' : 's'
                ));
            }
            $this->newLine();
        }

        // ⭐ The counts that caught real defects get their own line, because a
        //   number nobody looks at is a number that drifts.
        $noConsume = array_filter($all, static fn ($m): bool => $m->consumes === []);
        $noEmit = array_filter($all, static fn ($m): bool => $m->emits === []);

        $this->line('— '.count($all).' modules · '
            .count($noConsume).' consume nothing · '
            .count($noEmit).' emit nothing');
        $this->line('⛔ Generated now, from the manifests. Not stored. If this disagrees with a');
        $this->line('   document, the document is wrong — that is what N-263-03 is for.');

        return self::SUCCESS;
    }

    /** @param list<\App\Doctor\Manifest> $all */
    private function events(array $all): int
    {
        $emit = [];
        $consume = [];
        foreach ($all as $m) {
            foreach ($m->emits as $e) {
                $emit[$e][] = $m->id;
            }
            foreach ($m->consumes as $e) {
                $consume[$e][] = $m->id;
            }
        }

        $tokens = array_unique(array_merge(array_keys($emit), array_keys($consume)));
        sort($tokens);

        $this->line('# EVENT GRAPH — '.count($tokens).' events');
        $this->newLine();

        $orphans = 0;
        $facts = 0;

        foreach ($tokens as $t) {
            $from = $emit[$t] ?? [];
            $to = $consume[$t] ?? [];

            if ($from === []) {
                $orphans++;
                $mark = '⛔';
            } elseif ($to === []) {
                $facts++;
                $mark = '·';   // a recorded fact — correct, not a defect
            } else {
                $mark = '→';
            }

            $this->line(sprintf(
                '  %s %-30s %-28s %s',
                $mark,
                $t,
                $from === [] ? 'EXTERNAL' : implode(',', $from),
                $to === [] ? '(recorded fact)' : implode(',', $to)
            ));
        }

        $this->newLine();
        $this->line("— {$orphans} with no emitter · {$facts} recorded facts with no subscriber");
        $this->line('⭐ P-208 is DIRECTIONAL. A recorded fact needs no subscriber and most events');
        $this->line('   are facts. Only the ⛔ line — consumed with no emitter — fails a build.');

        return self::SUCCESS;
    }

    /** @param list<\App\Doctor\Manifest> $all */
    private function tables(array $all): int
    {
        $owner = [];
        $readers = [];
        foreach ($all as $m) {
            foreach ($m->ownsTable as $t) {
                $owner[$t][] = $m->id;
            }
            foreach ($m->readsTable as $t) {
                $readers[$t][] = $m->id;
            }
        }
        ksort($owner);

        $this->line('# TABLE OWNERSHIP — '.count($owner).' tables');
        $this->newLine();

        $contested = 0;
        foreach ($owner as $t => $owners) {
            // ⛔ Two owners is the defect that hid identity_links: X-134 owned it
            //    while X-132 owned a competing person_links, and the same person
            //    was resolved twice, in two tables, by two modules.
            $mark = count($owners) > 1 ? '⛔' : ' ';
            if (count($owners) > 1) {
                $contested++;
            }
            $r = $readers[$t] ?? [];
            $this->line(sprintf(
                '  %s %-28s owned by %-22s read by %s',
                $mark,
                $t,
                implode(',', $owners),
                $r === [] ? '—' : implode(',', $r)
            ));
        }

        $this->newLine();
        $this->line("— {$contested} table(s) with MORE THAN ONE OWNER");
        if ($contested > 0) {
            $this->line('⛔ Two owners means one row written by two modules with different rules.');
            $this->line('   P-163 forbids it for the canonical nouns; the same logic applies below them.');
        }

        return self::SUCCESS;
    }
}
