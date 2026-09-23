<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\DeclarationParser;
use App\Doctor\ManifestReader;
use Illuminate\Console\Command;

/**
 * `context <module>` — everything an agent needs to work on ONE module,
 * and nothing about any other.
 *
 * ⭐⭐⭐ N-263-01 IS THE ONLY REQUIREMENT THAT MATTERS HERE:
 *
 *     "context NEVER returns another module's brief — proven by asking for
 *      X-180 and diffing against X-186 (THE COLLISION THAT ACTUALLY HAPPENED)."
 *
 * ⛔⛔ That parenthesis is the whole reason this class is careful. It is not a
 * hypothetical. A context lookup returned the wrong module's content, and an
 * agent would have built X-180 to X-186's contract — producing code that
 * compiles, tests that pass, and a module that implements the wrong thing.
 *
 * ⭐ The defence is structural rather than diligent: this command loads exactly
 * ONE manifest, by exact id match, and every line it prints comes from that
 * object. There is no "related modules" section, no fuzzy match, no "did you
 * mean". A near-miss must FAIL, because a helpful near-miss is precisely how
 * X-180 became X-186.
 */
final class ContextCommand extends Command
{
    protected $signature = 'context {module : exact module id — no fuzzy matching, by design}';

    protected $description = 'One module\'s full context. Never another\'s — N-263-01.';

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = (string) $this->argument('module');

        // ⛔ EXACT MATCH ONLY. Not case-insensitive, not prefix, not closest.
        //   `X-18` must not resolve to `X-180`, and `X-180` must never resolve
        //   to `X-186` — which is exactly the substring-shaped mistake that
        //   produced the collision N-263-01 is named after.
        $me = null;
        foreach ($this->manifests->all() as $m) {
            if ($m->id === $id) {
                $me = $m;
                break;
            }
        }

        if ($me === null) {
            $this->error("No module with the exact id '{$id}'.");
            $this->line('  ⛔ No suggestions offered, deliberately. A near-miss suggestion is how');
            $this->line('     X-180 once returned X-186\'s content (N-263-01). Check `map` for the');
            $this->line('     exact id and ask again.');

            return self::FAILURE;
        }

        // ⭐ Every line below reads from $me. Nothing re-queries by name, because
        //   a second lookup is a second chance to resolve the wrong module.
        $this->line("# CONTEXT — {$me->id}");
        $this->newLine();
        $this->line("  intent    {$me->intent}");
        $this->line("  ships     {$me->shipsAt}   ceiling {$me->ceiling} (earned, never set)");
        $this->newLine();
        $this->line('## ITS CONTRACT');
        $this->line('  provides    '.$this->list($me->provides));
        $this->line('  emits       '.$this->list($me->emits));
        $this->line('  consumes    '.$this->list($me->consumes));
        $this->line('  owns_table  '.$this->list($me->ownsTable));
        $this->line('  reads_table '.$this->list($me->readsTable));
        $this->line('  renders     '.$this->list($me->renders));
        $this->newLine();

        // ⭐ The wildcard is a real declaration for the four spine modules and
        //   must be shown as one — not silently rendered as "nothing".
        if (in_array('*', $me->consumes, true)) {
            $this->line('  ⭐ This module consumes the WILDCARD: every event on the bus.');
            $this->line('     Legal for the spine modules only, and declared — not assumed.');
            $this->newLine();
        }

        $this->line('## THE ASSERTIONS IT MUST SATISFY');
        $specs = $this->specs($me->id);
        if ($specs === []) {
            $this->line('  ⛔ NONE — this module cannot be briefed (LAW 128\'s floor).');
        }
        foreach ($specs as $sid => $assertion) {
            $this->line("  [{$sid}] ".($assertion !== '' ? $assertion : '⚠️ no refusal declared'));
        }

        $this->newLine();
        $this->line("⛔ This output describes {$me->id} and no other module. If you see an id");
        $this->line('   other than that above, stop and report it — N-263-01.');

        return self::SUCCESS;
    }

    /** @param list<string> $v */
    private function list(array $v): string
    {
        return $v === [] ? 'none' : implode(' · ', $v);
    }

    /** @return array<string, string> */
    private function specs(string $module): array
    {
        $f = base_path("app/Modules/{$module}/capabilities.php");
        if (! is_file($f)) {
            return [];
        }

        /** @var array<string, string> $c */
        $c = require $f;

        return $c;
    }
}
