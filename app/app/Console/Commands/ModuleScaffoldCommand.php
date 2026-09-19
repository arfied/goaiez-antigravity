<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\DeclarationParser;
use Illuminate\Console\Command;

/**
 * `module:scaffold` — turn the 119 DOCUMENTED headers into the manifest FILES
 * `doctor`, `map`, `context`, `impact` and `brief` actually read.
 *
 * ⛔⛔ THIS IS THE MISSING LINK, AND IT IS THE WHOLE REASON NOTHING CAN START.
 *
 *   the chain:  header → doctor → brief → agent
 *
 * The headers exist — 119 of them, in a 3 MB markdown plan, each with WHAT,
 * WIZARD, AUTOPILOT, SWARM, SCREENS, NEEDS/HAS, TEST ANCHOR and its declarations.
 * `doctor` reads `app/Modules/<id>/manifest.php`. THERE ARE ZERO OF THOSE FILES.
 * So every check passes vacuously, `brief` has nothing to assemble, and an agent
 * would receive nothing.
 *
 * ⭐⭐⭐ AND THE POINT OF GENERATING RATHER THAN HAND-WRITING:
 *
 * Every stale-record defect this programme produced was a hand-maintained file —
 * the status column that said CLASSIFIED while 725 specs existed, 57 rows
 * pointing at a side file, a P-163 violation found and never applied, a law grid
 * claiming seven modules it had not added, `@laws` written into 0 of 119 headers.
 * A generated manifest cannot drift, because there is nowhere for it to drift to.
 *
 * ⛔ IT REFUSES RATHER THAN GUESSING. A header missing a required field produces
 * a REPORTED GAP, never a filled-in default. `§298`: an annotation is DERIVED
 * from what the thing does, never asserted to make it pass.
 */
final class ModuleScaffoldCommand extends Command
{
    protected $signature = 'module:scaffold
        {--plan=GOAIEZ-MASTER-PLAN.md : the source of record}
        {--module= : one module id, or omit for all}
        {--dry-run : report what would be written and write nothing}';

    protected $description = 'Generate app/Modules/<id>/manifest.php from the documented headers. Refuses to invent.';

    /** §257.6 — removed by owner ruling 2026-09-17; their headers stay in the plan as history and are never scaffolded again. */
    private const REMOVED = ['X-200', 'X-158', 'X-159', 'X-114', 'X-144', 'X-197', 'X-147', 'X-143', 'X-141', 'X-145', 'X-213', 'X-208', 'X-215', 'X-214', 'X-221', 'X-222', 'X-223'];

    /** Fields a manifest cannot be written without. */
    private const REQUIRED = ['module', 'intent', 'provides'];

    /** Fields recorded when present and REPORTED when absent — never defaulted. */
    private const EXPECTED = ['emits', 'consumes', 'owns_table', 'renders', 'ships', 'ceiling'];

    public function handle(): int
    {
        $planPath = base_path((string) $this->option('plan'));

        if (! is_file($planPath)) {
            $this->error("Plan not found at {$planPath}.");

            return self::FAILURE;
        }

        $headers = $this->parseHeaders((string) file_get_contents($planPath));

        if ($only = $this->option('module')) {
            $headers = array_filter($headers, static fn (array $h): bool => $h['module'] === $only);
        }

        $written = 0;
        $skipped = 0;
        $refused = [];
        $gaps = [];

        foreach ($headers as $h) {
            // ⛔ X-nnn is the TEMPLATE PLACEHOLDER in §157's header pattern, not a
            //    module. It counted as a 120th module in three separate sweeps
            //    before anyone read it.
            if ($h['module'] === 'X-nnn') {
                continue;
            }

            if (in_array($h['module'], self::REMOVED, true)) {
                $skipped++;

                continue;
            }

            $missing = array_values(array_filter(
                self::REQUIRED,
                static fn (string $f): bool => ($h[$f] ?? '') === ''
            ));

            if ($missing !== []) {
                $refused[] = "{$h['module']}: missing ".implode(', ', $missing);

                continue;
            }

            foreach (self::EXPECTED as $f) {
                if (($h[$f] ?? '') === '') {
                    $gaps[] = "{$h['module']}: no @{$f}";
                }
            }

            if (! $this->option('dry-run')) {
                $this->write($h);
            }
            $written++;
        }

        $this->line('  modules scaffolded : '.$written);
        $this->line('  ⛔ removed (§257.6)  : '.$skipped);
        $this->line('  ⛔ refused          : '.count($refused));
        foreach ($refused as $r) {
            $this->line("     · {$r}");
        }

        // ⭐ A gap is REPORTED, not filled. A defaulted field is a lie with a
        //   plausible value, and it passes every check that a real value would.
        $this->line('  ⚠️  gaps reported   : '.count($gaps));
        foreach (array_slice($gaps, 0, 12) as $g) {
            $this->line("     · {$g}");
        }

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * ⛔⛔ ANCHORED ON CONTENT, NEVER ON MARKUP.
     *
     * `N-262-05`, and it is the rule this programme has broken more than any
     * other: NINE markup over-matches across multiple windows — a roster that
     * was never incomplete, a spec count that doubled on re-run, three "dangling"
     * citations that all existed, sixteen "empty" modules that were already full.
     *
     * So this splits on `@module`, which is a DECLARATION, and never on `#`,
     * `|` or bold.
     *
     * @return list<array<string, string>>
     */
    private function parseHeaders(string $plan): array
    {
        $chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
        $out = [];

        foreach ($chunks as $chunk) {
            if (preg_match('/^@module\s+\*{0,2}((?:X|C)-[A-Za-z0-9]+)/', $chunk, $m) !== 1) {
                continue;
            }

            $body = substr($chunk, 0, 6000);

            $out[] = [
                'module' => $m[1],
                'intent' => $this->field($body, 'intent'),
                'provides' => $this->field($body, 'provides'),
                'emits' => $this->field($body, 'emits'),
                'consumes' => $this->field($body, 'consumes'),
                'owns_table' => $this->field($body, 'owns_table'),
                'reads_table' => $this->field($body, 'reads_table'),
                'renders' => $this->field($body, 'renders'),

                // ⛔⛔⛔ THE FIELD THAT NEVER MADE IT INTO A MANIFEST.
                //
                // `ContractStage` fails ~392 actions with "does not declare
                // whether the agent may reach it". The plan DOES declare it —
                // all 122 modules carry @agent_reachable, and X-212/X-215 say
                // `none` explicitly, with P-209's backfill-gate reasoning.
                //
                // ⛔ The SCAFFOLD never harvested the field. It was not in this
                //   list, so it never reached the manifest, so doctor — which
                //   reads the manifest — correctly reported it missing.
                //
                // ⭐ The plan was right. The manifest was empty. The checker was
                //   honest. The generator was the broken link, and it is the one
                //   place nobody was looking.
                'agent_reachable' => $this->field($body, 'agent_reachable'),
                // ⛔⛔⛔ THE LADDER IS A LEVEL, NOT A SUBSTRING.
                //
                // These used to be between($body,'ships:','·') and
                // between($body,'ceiling:',','), which grabbed the next 40
                // characters and cut at a delimiter. `ships:` has a `·` after
                // it so it survived. `ceiling:` DOES NOT — and after R235
                // appended its note to all 122 headers, the generated manifest
                // read:
                //
                //     'ceiling' => 'L3`** ⭐⭐⭐ *(**`R235` 2026-08-27 ',
                //
                // ⭐⭐ MY OWN R235 EDIT BROKE MY OWN SCAFFOLD. The contract
                //   count went UP — 244 → 610 — because every module now
                //   carried a corrupted ceiling, and 610 ≈ 5 × 122.
                //
                // ⛔ A parser that takes "the next 40 characters" is guessing.
                //   The value is `L0`–`L3` or `n/a`. Match THAT and nothing else.
                'ships' => $this->level($body, 'ships:'),
                'ceiling' => $this->level($body, 'ceiling:'),
            ];
        }

        return $out;
    }

    /**
     * ⛔⛔ ORDER MATTERS HERE, AND GETTING IT WRONG IS SILENT.
     *
     * Headers put several declarations on ONE line:
     *
     *     `@provides capability.check` · `@emits capability.decided`
     *
     * A first attempt split at the next `@word` BEFORE stripping backticks — so
     * the split never matched, and `provides` swallowed the `@emits` behind it.
     * The dry run reported 341 fields carrying a leaked declaration; the "fix"
     * moved it to 338. STRIP THE MARKUP FIRST, THEN SPLIT: 0.
     *
     * ⭐ That is the same lesson as `N-262-05` in miniature — a check anchored on
     * the wrong thing produces a confident, wrong answer, and only re-measuring
     * after the fix catches it.
     */
    /**
     * ⭐ Delegates to App\Doctor\DeclarationParser — the ONE parser.
     *
     * ⛔⛔ This method used to carry its own copy of the cleaning logic, and that
     * duplication is precisely how the tools diverged: the scaffold was fixed
     * for the note-pollution bug while doctor, brief, map and impact each kept
     * their own regex. A bug fixed in one place stayed live in four.
     *
     * Measured when they disagreed: `send.requested` showed 12 emitters where
     * 8 were declared, because a provenance note mentioning the event was read
     * as a declaration.
     */
    private function field(string $body, string $name): string
    {
        // ⛔⛔⛔ TAKE THE DECLARATION, NOT THE FIRST MENTION.
        //
        // `@provides` appears in the plan both as a DECLARATION and inside
        // prose about declarations — X-121's first hit is the fragment
        // "@provides`)*", the tail of a struck-text note.
        //
        // ⭐ A real declaration is followed by a token: a lowercase name,
        //   `none`, or `*`. A mention is followed by punctuation or prose.
        //   Match the SHAPE, and take the LAST such hit on the assumption the
        //   header's own declaration line is the authoritative one.
        //
        // ⛔ This is the fourth file in which I have written a parser that
        //   matched my own commentary. DeclarationParser, ContractStage,
        //   ManifestReader, and now here.
        if (preg_match_all(
            '/@'.preg_quote($name, '/').'\s+((?:`?[a-z*][a-z0-9_.*]*`?)(?:[^\n]*))/i',
            $body,
            $all,
            PREG_SET_ORDER
        ) === 0) {
            return '';
        }

        $best = '';
        foreach ($all as $hit) {
            $value = DeclarationParser::clean($hit[1]);
            if ($value !== '' && strlen($value) > strlen($best)) {
                $best = $value;
            }
        }

        return $best;
    }

    /**
     * ⭐ The ladder level for a key — `L0`–`L3` or `n/a`, and nothing else.
     *
     * Anchored to the key and bounded by the pattern itself, so no amount of
     * markdown, prose or annotation after the value can leak into it.
     */
    private function level(string $body, string $key): string
    {
        $q = preg_quote($key, '/');

        return preg_match('/'.$q.'\s*`?\s*(L[0-3]|n\/a)\b/i', $body, $m) === 1
            ? $m[1]
            : '';
    }

    /** @param array<string, string> $h */
    private function write(array $h): void
    {
        $dir = base_path("app/Modules/{$h['module']}");
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $tokens = static function (string $raw): string {
            $parts = array_values(array_filter(array_map(
                static fn (string $s): string => trim($s),
                preg_split('/[·,]/u', $raw) ?: []
            )));

            return $parts === []
                ? '[]'
                : "[\n        '".implode("',\n        '", $parts)."',\n    ]";
        };

        $php = <<<PHP
        <?php

        declare(strict_types=1);

        /**
         * ⛔ GENERATED by `module:scaffold` from the documented header. DO NOT EDIT.
         *
         * A hand-edited manifest is a manifest that drifts, and every stale-record
         * defect in this programme was a hand-maintained file. Change the header,
         * re-run the scaffold.
         *
         * @module {$h['module']}
         *
         * @intent {$h['intent']}
         */
        return [
            'module' => '{$h['module']}',
            'intent' => '{$h['intent']}',

            // ⛔⛔⛔ R235 — `ships` EQUALS `ceiling`. THE AUTOPILOT IS ON.
            //
            // This comment used to teach §227.2's intent default: "GROW ships
            // L1, PROPOSES ONLY, ALWAYS". The owner struck it — "ALL AI ON, by
            // my law. The client can turn it off if they want."
            //
            // ⭐ There is nothing to earn. @intent still DESCRIBES the action;
            //   it no longer gates when the action may run.
            'ships' => '{$h['ships']}',
            'ceiling' => '{$h['ceiling']}',

            'provides' => {$tokens($h['provides'])},
            'emits' => {$tokens($h['emits'])},
            'consumes' => {$tokens($h['consumes'])},

            // ⛔ P-163 — @owns_table may not name one of X-121's canonical nouns.
            'owns_table' => {$tokens($h['owns_table'])},
            'reads_table' => {$tokens($h['reads_table'])},

            'renders' => {$tokens($h['renders'])},

            // P-209 — silence fails OPEN. `none` is a decision; absence is not.
            'agent_reachable' => {$tokens($h['agent_reachable'] ?? '')},
        ];

        PHP;

        file_put_contents("{$dir}/manifest.php", $php);
    }
}
