<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

use Symfony\Component\Finder\Finder;

/**
 * STAGE ① BOUNDARY — pure grep, ~2s, FAILS THE COMMIT.
 *
 * ⛔ This stage is deliberately the cheapest thing in the pipeline, because a
 * check that costs nothing is a check nobody is tempted to skip. Over 100 of
 * the package's assertions live here.
 */
final class BoundaryStage implements Stage
{
    /** ⛔ P-204: the platform measures work, it does not pay people. */
    private const WAGE_FIELDS = ['gross', 'net_pay', 'withholding', 'tax_withheld', 'pay_run'];

    /** ⛔ P-194: models and providers are ROWS in ai_models / ai_providers. */
    /**
     * ⛔⛔ R237 — EVERY VENDOR, INCLUDING THE ONES NOT INVENTED YET.
     *
     * The first list held seven literals and named THREE vendors. The owner:
     * "the system needs to support ALL models — Grok, Gemma, DeepSeek, ChatGPT,
     * Claude, local self-hosted, anything we choose."
     *
     * ⛔ A checker that knows only OpenAI, Anthropic and Google will pass a file
     *   that hardcodes `grok-2` — and the enum it was written to kill just moves
     *   to a vendor the checker never heard of.
     *
     * ⭐ So this is a PREFIX list, not a model list. New models from a known
     *   vendor are caught automatically; only a genuinely new vendor needs a
     *   line here.
     */
    private const MODEL_PREFIXES = [
        'gpt-', 'o1-', 'o3-', 'o4-',            // OpenAI
        'claude-',                               // Anthropic
        'gemini-', 'gemma-',                     // Google
        'grok-',                                 // xAI
        'deepseek-',                             // DeepSeek
        'llama-', 'mistral-', 'mixtral-',        // open weights
        'qwen-', 'command-r',                    // Alibaba · Cohere

        // ⛔⛔⛔ 'phi-' IS DELIBERATELY ABSENT, AND THE REASON MATTERS.
        //
        // Microsoft ships models called Phi. But in THIS platform "PHI" means
        // PROTECTED HEALTH INFORMATION — PhiTenants, PhiAnalysisConsent,
        // PhiExclusion, PhiSchemaIsolation, BaaRecords, the whole HIPAA fence.
        //
        // The prefix flagged 'phi-analysis-2026-08-16.1' in TWO files. That is
        // a CONSENT VERSION STRING for health-data analysis, and calling it a
        // hardcoded model would have sent someone to "fix" a compliance
        // artifact by routing it through C-Ai.
        //
        // ⭐ A false positive on a HIPAA consent record is not a nuisance —
        //   it is an instruction to break the fence. Microsoft Phi is not
        //   currently in the roster; if it ever is, it needs a longer prefix
        //   ('phi-3', 'phi-4') that cannot collide with the health fence.
    ];

    /** ⛔ §262 stage ⑤: nothing random may feed an external-artifact-id field. */
    private const FORGERY = ['uniqid(', 'Str::random(', 'mt_rand(', 'rand('];

    public function run(): array
    {
        $out = [];
        foreach ($this->phpFiles() as $file) {
            $path = $file->getRelativePathname();
            $src = $file->getContents();
            $module = $this->moduleOf($path);

            // ⛔ Cross-module import. Cross-module change is an EVENT or a
            //    REGISTERED ACTION — never a `use`. A boundary violation caught
            //    at merge has already been built on by three other agents.
            // @boundary-fix-2026-09-08
            //
            // ⭐ THE SEAMS THE RULE ITSELF NAMES. The comment above states the rule: cross-module
            //   change is "an EVENT or a REGISTERED ACTION". So an `Events\` import IS the
            //   compliance, not the breach — a Laravel listener must name the event class to bind
            //   to it, and flagging it punishes the exact pattern this stage demands. `Actions\`
            //   is the other named seam. `Domain\` is a service seam: four of the nine measured on
            //   2026-09-07 were every outbound channel consulting X-204's ConsentService, which is
            //   a chokepoint working as designed.
            //
            // ⛔ `Models\` is NOT a seam and stays flagged — that is one module reading another
            //   module's tables. The house remedy already exists: C-Agent does not read X-01's
            //   `takeover_latches`, it keeps a projection fed by X-01's events.
            foreach ($this->imports($src) as [$imported, $kind]) {
                if (in_array($kind, ['Events', 'Actions', 'Domain'], true)) {
                    continue;
                }
                if ($module !== null && $imported !== '' && $imported !== $module) {
                    $out[] = [
                        'where' => $path,
                        'what' => "imports {$imported} across a module boundary",
                        'fix' => "emit an event, or invoke {$imported}'s registered action — never `use`",
                    ];
                }
            }

            // ⛔ P-194 / R237 — a model string in code is a model nobody can
            //    change without a deploy. §176 measured the old way: SEVEN code
            //    edits and a deploy to add ONE model, with Gemini, Grok, Gemma
            //    and Luna absent from the code entirely.
            foreach (self::MODEL_PREFIXES as $prefix) {
                if (preg_match('/[\'"]'.preg_quote($prefix, '/').'[a-z0-9.\\-]*[\'"]/i', $src, $hit)) {
                    $out[] = [
                        'where' => $path,
                        'what' => "hardcodes the model string {$hit[0]}",
                        // ⭐⭐⭐ THE FIX STRING THE OWNER CAUGHT AS WRONG.
                        //
                        // It used to say "read it from the ai_models table
                        // (P-194)" — which is right about the table and WRONG
                        // about how a module gets a model. A module never reads
                        // ai_models. It asks C-Ai, which resolves the row.
                        //
                        // ⛔ A fix string that names the wrong mechanism sends
                        //   someone to write a direct query against a spine
                        //   table, which is the P-163 violation one layer down.
                        'fix' => 'R237: ask C-Ai for the model — $ai->for($moduleId, $jobClass, $slot) '
                            .'where slot is PRIMARY, BACKUP or COMPLEX. The row lives in '
                            .'ai_module_assignments; the module never names a vendor and never '
                            .'queries ai_models directly.',
                    ];
                }
            }

            // ⛔ P-193 — every operational value is a ROW, changeable in admin with NO deploy.
            if (! str_starts_with($path, 'config/') && preg_match('/\benv\s*\(/', $src)) {
                $out[] = [
                    'where' => $path,
                    'what' => 'calls env() outside config/',
                    'fix' => 'move it to config/ and read config(); operational values are rows (P-193)',
                ];
            }

            // ⛔ P-204 — the cleanest way to never produce a wrong wage is to
            //    never produce a wage.
            //
            // ⭐⭐ A file that FORBIDS a term must name it. `BriefCommand` holds
            //   the fenced-term table:
            //
            //       'withholding' => 'P-204',
            //
            //   and the first run flagged it for containing the word it exists
            //   to ban. A rule and a violation of that rule are not the same
            //   thing, and a checker that cannot tell them apart flags the
            //   enforcement as the offence.
            $isFence = str_contains($src, 'FENCED')
                || str_contains($src, 'P-204 —')
                || str_contains($src, 'WAGE_FIELDS');
            foreach (self::WAGE_FIELDS as $f) {
                if (! $isFence && preg_match('/[\'"]'.preg_quote($f, '/').'[\'"]/', $src)) {
                    $out[] = [
                        'where' => $path,
                        'what' => "names a wage field '{$f}'",
                        'fix' => 'P-204/R193: the export is THREE things — hours, TIME TRACKING and commission. Wages are the employer\u2019s payroll',
                    ];
                }
            }

            // ⛔⛔ The fabricated artifact id. This is the check that would have
            //     caught VAPI_CALL_ID_{uniqid()} — the class invented the one
            //     artifact the Definition of Done says cannot be invented.
            if (preg_match('/(call_?id|message_?id|charge_?id|artifact_?id)/i', $src)) {
                foreach (self::FORGERY as $fn) {
                    if (str_contains($src, $fn)) {
                        $out[] = [
                            'where' => $path,
                            'what' => "generates a value with {$fn} in a file that handles external artifact ids",
                            'fix' => 'REFUSE before the request when the credential is absent; return the vendor\'s real id or nothing',
                        ];
                    }
                }
            }

            // ⛔ A default arm on an ENUM match hides the case nobody added.
            //
            // ⭐⭐⭐ BUT `match (true)` IS NOT AN ENUM MATCH. It is a chained
            //   conditional, and its `default` is the else branch — mandatory,
            //   not a hole. The first run flagged ImpactCommand for exactly
            //   this, and the code was correct:
            //
            //       return match (true) {
            //           preg_match('/^(X|C)-/', $s) => $this->module(...),
            //           str_contains($s, '.')       => $this->event(...),
            //           default                     => $this->tableImpact(...),
            //       };
            //
            // ⛔ Flagging that teaches people the checker is noise, which is how
            //   a real default arm later gets ignored.
            if (preg_match('/match\s*\(\s*true\s*\)/', $src) !== 1
                && preg_match('/match\s*\([^)]*\)\s*\{[^}]*default\s*=>/s', $src)) {
                $out[] = [
                    'where' => $path,
                    'what' => 'match() carries a default arm',
                    'fix' => 'enumerate every case; a default arm silently absorbs the enum value added next week',
                ];
            }
        }
        // ⛔⛔⛔ BASE-CLASS COLLISIONS — THE BUG THAT KILLED THE WHOLE TREE.
        //
        // `private function table()` in ImpactCommand overrode the PUBLIC
        // Illuminate\Console\Command::table(). PHP refuses to load such a class
        // at all, which killed `package:discover` — so EVERY artisan command on
        // the tree died, including composer install. One private method took the
        // platform down, and nothing in this stage would have caught it.
        //
        // ⭐ A subclass may override these ONLY as public. Anything narrower is
        //   a fatal error at CLASS LOAD, not at call time — which is why it
        //   cannot be found by testing the command itself.
        $baseApi = [
            'table', 'line', 'info', 'error', 'warn', 'comment', 'question', 'alert',
            'newLine', 'ask', 'askWithCompletion', 'secret', 'confirm', 'choice',
            'anticipate', 'withProgressBar', 'task', 'call', 'callSilent', 'callSilently',
            'option', 'options', 'argument', 'arguments', 'hasOption', 'hasArgument',
            'output', 'getOutput', 'components', 'fail', 'trap', 'run', 'execute',
            'configure', 'initialize', 'interact', 'setLaravel', 'getLaravel',
        ];
        foreach (glob(base_path('app/Console/Commands/*.php')) ?: [] as $path) {
            $src = (string) file_get_contents($path);
            if (preg_match_all('/^\s*(private|protected)\s+function\s+(\w+)\s*\(/m', $src, $hits, PREG_SET_ORDER) === 0) {
                continue;
            }
            foreach ($hits as $hit) {
                if (! in_array($hit[2], $baseApi, true)) {
                    continue;
                }
                $out[] = [
                    'where' => basename($path).' — '.$hit[1].' function '.$hit[2].'()',
                    'what' => "overrides Illuminate\\Console\\Command::{$hit[2]}() with weaker visibility",
                    'fix' => "rename it (e.g. {$hit[2]}Impact) or make it public. PHP refuses to LOAD "
                        .'the class otherwise, which kills package:discover and every artisan '
                        .'command on the tree — not just this one.',
                ];
            }
        }
        // ⛔⛔⛔ R242 — A MODULE SPLIT ACROSS TWO DIRECTORIES.
        //
        // A PHP namespace cannot contain a hyphen, so `app/Modules/X-126/`
        // cannot hold PSR-4 code. The first agent to build a module hit this and
        // did the only thing PSR-4 allows: it created `app/Modules/X126/` and
        // moved the code there.
        //
        // ⛔ The manifest stayed in X-126. Every gate reads app/Modules/{id}
        //   WITH the hyphen. So the code became invisible to the checks that
        //   must approve it — and nothing said so.
        //
        // ⭐⭐⭐ The failure here was SILENCE. This makes it loud.
        foreach (glob(base_path('app/Modules/*'), GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (str_contains($name, '-')) {
                continue;
            }

            // A directory whose name is a module id with the hyphen removed.
            if (preg_match('/^([XC])([0-9]+)$/', $name, $hit) !== 1) {
                continue;
            }

            $withHyphen = $hit[1].'-'.$hit[2];
            if (! is_dir(base_path('app/Modules/'.$withHyphen))) {
                continue;
            }

            $out[] = [
                'where' => 'app/Modules/'.$name,
                'what' => "module {$withHyphen} is SPLIT across two directories — its code is invisible to every gate",
                'fix' => "move it back: mv app/Modules/{$name}/* app/Modules/{$withHyphen}/ && rmdir app/Modules/{$name}. "
                    .'R242: the directory keeps the hyphen and holds BOTH manifest and code; classes are '
                    .'autoloaded by CLASSMAP (composer.json: "classmap": ["app/Modules/"]), which has no '
                    .'naming constraint. Then composer dump-autoload.',
            ];
        }



        return $out;
    }

    /** @return list<string|null> */
    /**
     * @boundary-fix-2026-09-08
     *
     * Returns [module, kind] so the caller can tell a SEAM from a reach-in. The second segment
     * is optional: an import naming no sub-namespace yields '' , which is not a seam and stays
     * flagged — the check fails CLOSED on a shape it does not recognise.
     *
     * @return list<array{0:string,1:string}>
     */
    private function imports(string $src): array
    {
        preg_match_all(
            '/^use\s+App\\\\Modules\\\\([A-Za-z0-9_]+)(?:\\\\([A-Za-z0-9_]+))?/m',
            $src,
            $m,
            PREG_SET_ORDER
        );

        return array_map(static fn (array $x): array => [$x[1], $x[2] ?? ''], $m);
    }

    private function moduleOf(string $path): ?string
    {
        // @boundary-fix-2026-09-08
        //
        // ⛔⛔⛔ THIS RETURNED NULL FOR ALMOST EVERY MODULE, AND THE CROSS-MODULE IMPORT CHECK IS
        //   GUARDED ON `$module !== null` — so that check silently scanned nothing at all. The
        //   character class had no hyphen and the directories are `X-102`, `C-Agent`, `X-01`.
        //   Measured 2026-09-07: 81 cross-module `use` statements existed and the stage
        //   reported 0. It was reported as a DEAD check and ruled for removal; it was BROKEN.
        //
        // ⭐ The hyphen is STRIPPED, not merely allowed: imports() yields the NAMESPACE segment
        //   (`X102`) because that is what a `use` carries, while the path yields the DIRECTORY
        //   (`X-102`). Allowing the hyphen alone would make every SAME-module import compare
        //   unequal and fire on everything.
        // @boundary-fix-2026-09-08b
        //
        // ⛔ THE `^app/` ANCHOR COULD NEVER MATCH. This is handed
        //   $file->getRelativePathname() from a Finder rooted at base_path('app'), so the paths
        //   arriving here are `Modules/X-102/Domain/Foo.php` and `Enums/AiModel.php` — no `app/`
        //   prefix. The stage's own violation messages show it (`· Modules/C-Mail/...`). That,
        //   not the hyphen, is what disabled the cross-module check outright.
        //
        // ⭐ The prefix is OPTIONAL so this stays correct whichever root a caller uses — the
        //   function no longer depends on the Finder's rooting to work at all.
        return preg_match('#^(?:app/)?Modules/([A-Za-z0-9_-]+)/#', $path, $m) === 1
            ? str_replace('-', '', $m[1])
            : null;
    }

    /**
     * ⛔⛔⛔ THE CHECKER MUST NOT CHECK ITSELF.
     *
     * On the FIRST REAL RUN this stage reported 82 violations, and THIRTEEN of
     * the first thirty were itself:
     *
     *   BoundaryStage.php: hardcodes the model string 'gpt-4'
     *   BoundaryStage.php: hardcodes the model string 'claude-3'
     *   BoundaryStage.php: names a wage field 'gross'
     *   TestAnchorStage.php: generates a value with uniqid(
     *
     * Those strings are the NEEDLES. `gpt-4`, `claude-3`, `gemini-1`, `uniqid(`,
     * `gross` — this stage hunts for them, so it necessarily contains them, so
     * it necessarily finds them in its own source.
     *
     * ⭐⭐⭐ This is the THIRD time this exact defect has appeared in this
     * programme: `M-95` passed a citation check because writing the finding down
     * put the id in the corpus; twenty legacy rulings "resolved" because the
     * ledger listing them became the evidence. Both were caught by hand. This is
     * the first time it appeared in CODE, and it is the same shape:
     *
     *   A TOOL THAT DESCRIBES A DEFECT WILL MATCH ITS OWN DESCRIPTION.
     *
     * ⛔ app/Doctor is excluded for that reason and no other. Everything else in
     *   app/ is fair game — including app/Console/Commands, which is NOT
     *   excluded, because a command that hardcodes a model string is a real
     *   violation and commands do not carry the pattern lists.
     */
    private function phpFiles(): Finder
    {
        return Finder::create()
            ->files()
            ->in(base_path('app'))
            ->name('*.php')
            // ⛔⛔⛔ `notPath('Doctor')` DID NOTHING — the self-scans came back.
            //
            // Symfony's notPath() matches the relative DIRECTORY path, and the
            // behaviour differs enough across versions that it silently matched
            // nothing here. A filter that quietly excludes zero files looks
            // exactly like a filter that works.
            //
            // ⭐ filter() takes a closure. There is no version-dependent
            //   pattern semantics to get wrong, and its behaviour is visible
            //   from reading it.
            ->filter(static fn (\SplFileInfo $f): bool => ! str_contains(
                str_replace('\\', '/', $f->getPathname()),
                '/app/Doctor/'
            ));
    }
}
