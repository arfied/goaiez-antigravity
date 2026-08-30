<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * `capabilities:scaffold` — turn the 776 tracker rows into the
 * `app/Modules/<id>/capabilities.php` files `doctor` and `brief` read.
 *
 * ⛔⛔ THE SECOND HALF OF THE SAME GAP.
 *
 * `module:scaffold` produced 119 manifests. But `CapabilityStage` and `brief`
 * both read a SECOND file — `capabilities.php` — and there are zero of those.
 * So `brief` would refuse all 119 modules on LAW 128's floor, and the
 * capability stage would report 119 modules with zero specs. The documentation
 * layer is complete; the FILES it must become are not.
 *
 * ⭐ 776 rows map to 102 modules. 17 modules have none, and this command
 * REFUSES them by name rather than emitting an empty file — because an empty
 * `capabilities.php` would satisfy the file check while failing the floor, and
 * a check that passes on an empty artifact is the whole defect this programme
 * has been chasing.
 *
 * ⛔⛔ AND IT READS THE ⑤ FROM THE RIGHT FILE — WHICH TOOK A DRY RUN TO LEARN.
 *
 * The first version took the assertion from the TRACKER's note column. That
 * column carries dispositions and corrections — "transcribed from the G1 audit",
 * "corpus says Twilio/tokens → Infobip" — not test contracts. 632 of 765
 * assertions came out EMPTY, 83%, against a tracker whose status column says 775
 * SPECCED. The numbers disagreed, so the source was wrong.
 *
 * ⭐ The ⑤ lives in the MASTER PLAN's capability tables, where each row's last
 * cell is the assertion: "X-198's MOCK gateway is asserted unreachable from a
 * live tenant". Re-sourced: 693 of 765 — 90%.
 *
 * ⛔ It still does not INVENT one. 72 entries remain empty and are REPORTED —
 * `§298`: derived from what the thing does, never asserted to make it pass.
 */
final class CapabilitiesScaffoldCommand extends Command
{
    protected $signature = 'capabilities:scaffold
        {--tracker=GOAIEZ-TRACKER-CAPABILITIES.md}
        {--dry-run}';

    protected $description = 'Generate capabilities.php from the tracker. Refuses modules with no rows.';

    public function handle(): int
    {
        $path = base_path((string) $this->option('tracker'));

        if (! is_file($path)) {
            $this->error("Tracker not found at {$path}.");

            return self::FAILURE;
        }

        $rows = $this->parse((string) file_get_contents($path));
        $roster = $this->roster();

        $written = 0;
        $noRefusal = [];

        foreach ($rows as $module => $caps) {
            if (! in_array($module, $roster, true)) {
                continue;
            }

            foreach ($caps as $id => $c) {
                if ($c['assertion'] === '') {
                    $noRefusal[] = "{$module} · {$id}";
                }
            }

            if (! $this->option('dry-run')) {
                $this->write($module, $caps);
            }
            $written++;
        }

        // ⛔⛔ THE 17. Named, not summarised — a count hides which ones, and
        //    "17 modules" is the kind of number that gets rounded away.
        $empty = array_values(array_diff($roster, array_keys($rows)));

        $this->line('  modules written            : '.$written);
        $this->line('  ⛔ REFUSED — no rows at all : '.count($empty));
        foreach ($empty as $m) {
            $this->line("     · {$m} — brief will refuse it on LAW 128's floor until it has ≥1 spec");
        }
        $this->line('  ⚠️  specs with no refusal    : '.count($noRefusal));
        foreach (array_slice($noRefusal, 0, 10) as $n) {
            $this->line("     · {$n} — a ⑤ that names no failure mode protects nothing");
        }

        return self::SUCCESS;
    }

    /**
     * ⛔ ANCHORED ON THE ROW'S OWN SHAPE, NOT ON MARKUP.
     *
     * `G-` rows carry their parent in the PARENT column; `N-` rows carry it in
     * the CAPABILITY column. A single column index would have silently missed
     * every `N-` row — which is exactly the mistake that once reported 16
     * "empty" modules that already held 86 property rows between them.
     *
     * @return array<string, array<string, array{assertion:string, status:string}>>
     */
    private function parse(string $tracker): array
    {
        $out = [];

        foreach (explode("\n", $tracker) as $line) {
            if (! str_starts_with($line, '|')) {
                continue;
            }

            $c = array_map('trim', explode('|', $line));

            if (preg_match('/^⭐?\s*\*{0,2}(G\d+-\d+|N-\d+(?:-\d+)?)/u', $c[1] ?? '', $m) !== 1) {
                continue;
            }
            $id = $m[1];

            $parentCell = ($c[4] ?? '') !== '' ? $c[4] : ($c[2] ?? '');
            $status = $c[5] ?? '';

            // ⛔ NOT the tracker's note column — that carries dispositions, not
            //    test contracts. The ⑤ comes from the plan's capability tables.
            $assertion = $this->assertions()[$id] ?? '';

            foreach (preg_split('/[·,]/u', $parentCell) ?: [] as $piece) {
                if (preg_match('/\b((?:X|C)-[A-Za-z0-9]+)\b/', $piece, $mm) === 1) {
                    $out[$mm[1]][$id] = ['assertion' => $assertion, 'status' => $status];
                }
            }
        }

        return $out;
    }

    /**
     * The ⑤ for every capability id, read from the MASTER PLAN's capability
     * tables — each row's last populated cell is its assertion.
     *
     * @return array<string, string>
     */
    private function assertions(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        $plan = base_path('GOAIEZ-MASTER-PLAN.md');
        if (! is_file($plan)) {
            return $cache;
        }

        foreach (explode("\n", (string) file_get_contents($plan)) as $line) {
            if (! str_starts_with($line, '|')) {
                continue;
            }
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) < 6) {
                continue;
            }
            if (preg_match_all('/\b(G\d+-\d+|N-\d+(?:-\d+)?)\b/', $cells[1] ?? '', $m) === 0) {
                continue;
            }
            $tail = array_values(array_filter(array_slice($cells, 2)));
            if ($tail === []) {
                continue;
            }
            $a = trim((string) preg_replace('/[⭐⛔⚠️✅*`]/u', '', (string) end($tail)));
            if (mb_strlen($a) < 12) {
                continue;
            }
            foreach ($m[1] as $id) {
                $cache[$id] ??= $a;
            }
        }

        return $cache;
    }

    /** @return list<string> */
    private function roster(): array
    {
        $plan = base_path('GOAIEZ-MASTER-PLAN.md');
        if (! is_file($plan)) {
            return [];
        }
        preg_match_all('/@module\s+\*{0,2}((?:X|C)-[A-Za-z0-9]+)/', (string) file_get_contents($plan), $m);

        return array_values(array_diff(array_unique($m[1] ?? []), ['X-nnn']));
    }

    /** @param array<string, array{assertion:string, status:string}> $caps */
    private function write(string $module, array $caps): void
    {
        $dir = base_path("app/Modules/{$module}");
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $body = '';
        foreach ($caps as $id => $c) {
            $a = str_replace("'", "\\'", $c['assertion']);
            $body .= "\n    // status: {$c['status']}\n    '{$id}' => '{$a}',\n";
        }

        $count = count($caps);

        $php = <<<PHP
        <?php

        declare(strict_types=1);

        /**
         * ⛔ GENERATED by `capabilities:scaffold`. DO NOT EDIT.
         *
         * {$count} capability(ies) for {$module}.
         *
         * ⭐⭐ THESE ARE THE ASSERTIONS THE AGENT MUST SATISFY — AND MUST NOT AUTHOR.
         *
         * P-210: an agent that writes both the code and the test from the same
         * brief, in the same reading, produces code that is wrong and a test that
         * agrees — and the suite goes green. Every ⑤ below was written against a
         * failure mode by somebody who was not building this module.
         *
         * ⛔ An id here with no matching test under tests/Modules/{$module}/ FAILS
         * THE BUILD. That is what stops an agent deleting an inconvenient one.
         *
         * ⚠️ An EMPTY assertion is a real gap, not a formality: it means the row
         * carries no refusal, and a ⑤ that only restates the ① protects nothing.
         */
        return [{$body}];

        PHP;

        file_put_contents("{$dir}/capabilities.php", $php);
    }
}
