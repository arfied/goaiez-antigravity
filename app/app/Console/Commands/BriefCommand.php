<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\Manifest;
use App\Doctor\ManifestReader;
use Illuminate\Console\Command;

/**
 * `brief <module>` — the ONLY thing a swarm agent ever receives.
 *
 * ⛔⛔ AN AGENT NEVER SEES THE MASTER PLAN. `P-209`: the module contract is a
 * FILE, and this command assembles a brief FROM THE ANNOTATIONS AND FROM NOTHING
 * ELSE. That is the entire reason 3 MB of documentation is safe to build from —
 * a plan that is wrong gets caught by `doctor` before it can become a brief.
 *
 * ⭐ FOUR REFUSALS, EACH BOUGHT WITH A REAL DEFECT:
 *
 *   ① NEVER OPENS THE REGISTER (`P-206`). The god-tier register was mined from a
 *      349-file corpus containing ecommerce, B2B SaaS and recruiting. Its own
 *      description of `Store Transfers` reads "if Miami is sold out but Orlando
 *      has 10 units, a retail…" — a shoe shop, in a product for plumbers. That
 *      sentence was removed from one file and reintroduced into another four
 *      turns later by someone transcribing carefully.
 *
 *   ② REFUSES A HEADER THAT CONTRADICTS A LAW. `X-168` advertised "overtime
 *      math · payroll export" for an entire session AFTER `P-204` fenced payroll
 *      — through eight gates, an audit, and a brief emission. The gates checked
 *      that a header EXISTED, not that it AGREED with the laws.
 *
 *   ③ REFUSES ON ZERO SPECS (`LAW 128`'s floor). 116 briefs were emitted and
 *      everyone worried they were too BIG. Zero were over 20k. TWENTY-FOUR had
 *      no capability specs at all — a brief that passes every gate and contains
 *      no requirements. A too-big brief is visible; a too-small one is short,
 *      tidy, and an agent will happily build something from it.
 *
 *   ④ RECORDS ITS OWN HASH. Proof the agent built from the CURRENT contract and
 *      not a stale copy someone had open.
 */
final class BriefCommand extends Command
{
    protected $signature = 'brief {module} {--out= : write to a file instead of stdout}';

    protected $description = 'Assemble the brief an agent receives. From the annotations, and from nothing else.';

    /** ⛔ Terms a module may not ADVERTISE. Fenced by P-204, P-128/§44, the footprint fence, the ATS fence. */
    private const FENCED = [
        'overtime' => 'P-204 — the platform measures work, it does not pay people',
        'payroll' => 'P-204',
        'gross pay' => 'P-204',
        'withholding' => 'P-204',
        'ad account' => 'P-128/§44 — ad management needs their account',
        'ad budget' => 'P-128/§44',
        'warehouse' => 'the footprint fence — a van and a storage unit, not a warehouse',
        'drop-ship' => 'the footprint fence',
        'applicant' => 'the ATS fence',
        'candidate' => 'the ATS fence',
    ];

    /**
     * ⛔ VOCABULARY COLLISIONS — declared BY MODULE AND BY TERM, never by pattern.
     *
     * Each entry is a word that means something else in that module. Naming them
     * individually keeps the list short, auditable, and impossible to widen by
     * accident — a regex exemption would have quietly re-admitted the real ones.
     *
     * @var array<string, array<string, string>>
     */
    private const COLLIDES = [
        'X-145' => ['candidate' => 'candidate ACTIONS from the registry, not a job applicant'],
    ];

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = (string) $this->argument('module');
        $m = $this->manifests->find($id);

        if ($m === null) {
            $this->error("{$id} has no manifest. Run `php artisan module:scaffold --module={$id}` first.");

            return self::FAILURE;
        }

        if (($refusals = $this->refusals($m)) !== []) {
            $this->error("REFUSED — {$id} cannot be briefed:");
            foreach ($refusals as $r) {
                $this->line("  ⛔ {$r}");
            }
            $this->newLine();
            $this->line('  A brief is not emitted for a module whose contract is wrong.');
            $this->line('  Fix the header, re-run `module:scaffold`, then brief.');

            return self::FAILURE;
        }

        $brief = $this->assemble($m);

        if ($out = $this->option('out')) {
            file_put_contents((string) $out, $brief);
            $this->info("Brief written to {$out} (".strlen($brief).' bytes).');

            return self::SUCCESS;
        }

        $this->line($brief);

        return self::SUCCESS;
    }

    /**
     * ⭐ The module's TEST ANCHOR, read from the master plan.
     *
     * ⛔ It lives in the PLAN, not in the manifest — the scaffold does not carry
     *   it across, because it is prose for a human and not a declaration. That
     *   is exactly why it must be surfaced here: prose the builder needs is
     *   useless if only the plan has it and the builder is reading a brief.
     */
    private function anchor(string $id): string
    {
        $plan = base_path('GOAIEZ-MASTER-PLAN.md');
        if (! is_file($plan)) {
            return '';
        }

        $src = (string) file_get_contents($plan);
        $start = strpos($src, '@module **'.$id.'**');
        if ($start === false) {
            $start = strpos($src, '@module '.$id);
        }
        if ($start === false) {
            return '';
        }

        $chunk = substr($src, $start, 7000);
        if (preg_match('/\*\*TEST ANCHOR\*\*(.{0,400}?)(?:\n\n|@module|---)/s', $chunk, $m) !== 1) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[⭐⛔⚠️✅*`]/u', '', $m[1])));
    }

    /** @return list<string> */
    private function refusals(Manifest $m): array
    {
        $out = [];

        // ③ LAW 128's FLOOR — the failure nobody was looking for.
        $specs = $this->specs($m->id);
        if ($specs === []) {
            $out[] = 'ZERO specced capabilities. A brief with no requirements passes every gate '
                .'and tells an agent nothing. (LAW 128 has a floor, not only a ceiling.)';
        }

        // ⑤ AN OWED DECLARATION IS NOT A DECLARATION.
        //
        // ⛔⛔ Twelve modules (X-202–X-215) were minted with a COMPRESSED inline
        //     header and @consumes was never written — one field, twelve modules,
        //     a batch mint rather than twelve separate oversights.
        //
        // Their prose names no trigger, so filling it in would be invention
        // (§298). They are marked OWED instead, and an OWED module CANNOT BE
        // BRIEFED: an agent handed a module that does not say what it listens to
        // will infer a trigger, and the inferred one will be wrong in a way that
        // passes every test written from the same brief (P-210).
        //
        // ⭐ "@consumes none" is a valid answer. Silence is not.
        if (str_contains($this->whatOf($m->id), 'OWED — NOT YET DECLARED')) {
            $out[] = 'its @consumes is OWED, not declared. A module that does not say what it '
                .'listens to cannot be briefed — an agent will infer a trigger and the inferred '
                .'one will be wrong. Name the events, or declare "@consumes none".';
        }

        // ② A HEADER THAT CONTRADICTS A LAW.
        $what = strtolower($this->whatOf($m->id));
        foreach (self::FENCED as $term => $law) {
            if (! str_contains($what, $term)) {
                continue;
            }
            // ⭐ §241's THREE outcomes, not two. A header that EXCLUDES a fenced
            //   term is doing its job and must pass — a naive two-outcome check
            //   flagged 6 modules and was wrong about 4 of them, and a check that
            //   cries wolf four times out of six is switched off by the fourth.
            // ⭐ NEGATION — the header says it does NOT do the thing.
            if (preg_match('/\b(no|never|not|out of scope)\b[^.]{0,40}'.preg_quote($term, '/').'/i', $what) === 1) {
                continue;
            }

            // ⭐⭐ PARKED / EXCLUDED — the header names the fenced thing precisely
            //    IN ORDER TO SAY IT IS OUT. X-167 writes "Warehouse depth stays
            //    PARKED — pick paths, FIFO, 3PL, kitting." That is the boundary
            //    doing its job out loud, and refusing it would teach everyone to
            //    stop writing boundaries down.
            if (preg_match('/'.preg_quote($term, '/').'[^.]{0,40}\b(parked|out of scope|fenced|deferred)\b/i', $what) === 1) {
                continue;
            }

            // ⭐⭐⭐ VOCABULARY COLLISION — the same word, a different thing.
            //    X-145 DecisioningStudio writes "candidate set = the action
            //    registry" — candidate ACTIONS, not a job applicant. §241's third
            //    outcome: fix the WORD, never the capability. A naive two-outcome
            //    check flagged 6 modules and was wrong about 4, and a check that
            //    cries wolf four times in six is switched off by the fourth.
            if (isset(self::COLLIDES[$m->id][$term])) {
                continue;
            }
            $out[] = "the header ADVERTISES '{$term}', which {$law} fences. "
                .'Strike it from the WHAT, or add @excludes if the module deliberately names it to say it does NOT do it.';
        }

        // ⭐⭐⭐ R235 — ships MUST EQUAL ceiling. The autopilot is ON.
        //
        // ⛔⛔ This check previously enforced §227.2's intent default (GROW → L1
        //    PROPOSE) and would have REFUSED ALL 122 MODULES the moment R235
        //    raised them — the entire platform unbriefable because a helper
        //    still believed a struck rule.
        if ($m->shipsAt !== 'n/a' && $m->ceiling !== '' && $m->shipsAt !== $m->ceiling) {
            $out[] = "ships at {$m->shipsAt} but its ceiling is {$m->ceiling}. R235: every autopilot "
                .'ships ON, at its ceiling. Nothing waits to be switched on and nothing earns the '
                .'right to act — the client turns it off if they want.';
        }

        return $out;
    }

    private function assemble(Manifest $m): string
    {
        $specs = $this->specs($m->id);
        $hash = substr(hash('sha256', $this->manifests->source($m)), 0, 12);

        $lines = [
            "# BRIEF — {$m->id}",
            '',
            "contract hash: {$hash}   ⛔ if this does not match `doctor`'s, you are building from a stale copy",
            '',
            '## WHAT YOU MAY DO',
            'Only what is listed. Cross-module change is an EVENT or a REGISTERED ACTION — never an import.',
            '',
            // ⛔⛔⛔ THE TEST ANCHOR BELONGS HERE, AND IT WAS MISSING.
            //
            // An agent lost its context mid-task and went looking for X-126's
            // TEST ANCHOR with `find . -name "*X-126*"`. That was a REASONABLE
            // thing to do and it could never have worked: the anchor is a line
            // in GOAIEZ-MASTER-PLAN.md, not a file.
            //
            // ⭐⭐⭐ `brief` exists to be the ONE thing an agent needs. It listed
            //   what the module may DO and never said HOW THE WORK IS PROVEN —
            //   so the single most important sentence for the builder was the
            //   one sentence the builder could not get.
            //
            // ⭐ Now it is in the brief, which means a fresh agent with no
            //   history has it in one command.
            '  provides   '.implode(' · ', $m->provides),
            '  emits      '.implode(' · ', $m->emits),
            '  consumes   '.implode(' · ', $m->consumes),
            '',
            '## HOW THIS IS PROVEN',
            '  TEST ANCHOR  '.($this->anchor($m->id) ?: '⛔ NONE IN THE PLAN — this module cannot be proven done'),
            '  owns_table '.implode(' · ', $m->ownsTable),
            '  renders    '.implode(' · ', $m->renders),
            '',
            '## AUTONOMY',
            "  ships at {$m->shipsAt} — ON from minute one (R235; @intent {$m->intent} describes the action, it does not gate it)",
            "  ceiling  {$m->ceiling} — R235: this EQUALS ships. There is nothing to earn.",
            '',
            '## THE ASSERTIONS YOU MUST SATISFY',
            '',
            '⛔ You do NOT author these. P-210: an agent that writes both the code and the test',
            '   from the same brief, in the same reading, produces code that is wrong and a test',
            '   that agrees — and the suite goes green. These were written against failure modes',
            '   by someone who was not building this module.',
            '',
            '⛔ You may not delete one you find inconvenient. Every id below must appear in a test',
            '   under tests/Modules/'.$m->id.'/. A missing id fails the build.',
            '',
        ];

        foreach ($specs as $id => $assertion) {
            $lines[] = "  [{$id}] {$assertion}";
        }

        $lines[] = '';
        $lines[] = '## DONE MEANS SEVEN THINGS';
        $lines[] = '  BUILT · TESTED (runtime proof + EXTERNAL artifact id) · CONTENT (rows, not literals)';
        $lines[] = '  HELP (generated from the manifest) · DASHBOARD (honest empty state) · ALL FUNCTIONS';
        $lines[] = '  · GATE (doctor green).   Run: php artisan doctor:module-done '.$m->id;
        $lines[] = '';
        $lines[] = '⛔ "Code merged" is not done. A task is complete when the evidence satisfies the DoD,';
        $lines[] = '   never when you say it is.';
        $lines[] = '';

        return implode("\n", $lines)."\n";
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

    private function whatOf(string $module): string
    {
        $f = base_path("app/Modules/{$module}/manifest.php");

        return is_file($f) ? (string) file_get_contents($f) : '';
    }
}
