<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\Stages\BoundaryStage;
use App\Doctor\Stages\IntegrityStage;
use App\Doctor\Stages\CapabilityStage;
use App\Doctor\Stages\CitationStage;
use App\Doctor\Stages\ContractStage;
use App\Doctor\Stages\JourneyStage;
use App\Doctor\Stages\SchemaStage;
use App\Doctor\Stages\TestAnchorStage;
use Illuminate\Console\Command;

/**
 * `doctor` — the checks, so no check is ever a prompt again (Q-089).
 *
 * ⛔ THIS COMMAND IS NOT NEW WORK. The package already contains 170 written
 * `doctor` assertions and 134 "fails the build" phrases. ZERO of them ran.
 * Every defect this programme produced — 25 invented symbols, a forged test
 * footer, a fabricated call id, a header that contradicted a law through eight
 * gates — was caught by a check that existed and was not run, or by no check.
 *
 * ⭐ SEVEN STAGES, CHEAPEST FIRST (§231), AND THREE DIFFERENT FAILURE SEMANTICS:
 *
 *   stages 0–3  fail the COMMIT   — with agents committing in parallel, a
 *                                    boundary violation that survives to merge
 *                                    has already been built on
 *   stages 3–4  fail the MERGE
 *   stage  5–6  fail the WAVE
 *
 * ⛔⛔ THERE IS NO --skip-checks. Not discouraged — ABSENT. A slow pipeline gets
 * bypassed: someone adds a skip flag for a hotfix, it becomes the habit, and six
 * months later the boundary law is a comment. That is why 100+ of the checks
 * live in stage 1, where they cost two seconds.
 *
 * ⭐ AND EVERY CHECK PROPOSES THE FIX. A check that only says no gets disabled;
 * one that says "add `@excludes X` to module Y" gets maintained.
 */
final class DoctorCommand extends Command
{
    /**
     * ⭐⭐⭐ THE BUILD STAMP. THIS EXISTS BECAUSE OF A REAL, REPEATED FAILURE.
     *
     * Three consecutive runs produced BYTE-IDENTICAL output — including a crash
     * on a line of code that no longer existed in the source. The files were
     * being re-downloaded into a download folder and never copied into the
     * Laravel tree, so artisan kept running the first version.
     *
     * ⛔ Nothing in the output said which build it was. "The count did not fall"
     *   is indistinguishable from "the fix did not work" when you cannot see
     *   WHICH CODE RAN.
     *
     * ⭐ Now every doctor run prints this. If it does not match the build you
     *   downloaded, you are looking at old results and no conclusion drawn from
     *   them is valid.
     */
    public const BUILD = '20260829-0647';

    protected $signature = 'doctor
        {--stage= : boundary|contract|citation|schema|capability|anchor|journey — omit to run all}
        {--json}';

    protected $description = 'Run the assertions the plan already declares. No skip flag exists.';

    /** @var array<string, class-string> */
    private const STAGES = [
        // ⭐⭐⭐ FIRST. If the checks themselves were edited, nothing below
        //   this line is a number you can trust.
        'integrity' => IntegrityStage::class,
        'boundary'   => BoundaryStage::class,   // ~2s   → fails the COMMIT
        'contract'   => ContractStage::class,   // ~5s   → fails the COMMIT
        'citation'   => CitationStage::class,   // ~3s   → fails the COMMIT
        'schema'     => SchemaStage::class,     // ~20s  → fails the MERGE
        'capability' => CapabilityStage::class, //       → fails the MERGE
        'anchor'     => TestAnchorStage::class, //       → fails the WAVE
        'journey'    => JourneyStage::class,    //       → fails the WAVE
    ];

    private const FAILS = [
        'integrity' => 'COMMIT',
        'boundary' => 'COMMIT', 'contract' => 'COMMIT', 'citation' => 'COMMIT',
        'schema' => 'MERGE',    'capability' => 'MERGE',
        'anchor' => 'WAVE',     'journey' => 'WAVE',
    ];

    public function handle(): int
    {
        $this->line('  goaiez doctor · build '.self::BUILD);
        $only = $this->option('stage');
        $stages = $only ? array_intersect_key(self::STAGES, [$only => true]) : self::STAGES;

        if ($stages === []) {
            $this->error("Unknown stage '{$only}'. One of: ".implode(', ', array_keys(self::STAGES)));

            return self::FAILURE;
        }

        $findings = [];
        foreach ($stages as $name => $class) {
            $started = microtime(true);
            /** @var \App\Doctor\Stages\Stage $stage */
            $stage = app($class);
            $result = $stage->run();
            $ms = (int) ((microtime(true) - $started) * 1000);

            $findings[$name] = ['fails' => self::FAILS[$name], 'ms' => $ms, 'violations' => $result];

            if (! $this->option('json')) {
                $count = count($result);
                $this->line(sprintf(
                    '  %s %-11s %5dms  %s',
                    $count === 0 ? 'ok  ' : 'FAIL',
                    $name,
                    $ms,
                    $count === 0 ? 'clean' : "{$count} violation(s) — fails the ".self::FAILS[$name]
                ));
                foreach ($result as $v) {
                    // ⭐ Every violation carries its FIX, not just its complaint.
                    $this->line("       · {$v['where']}: {$v['what']}");
                    $this->line("         fix: {$v['fix']}");
                }
            }
        }

        $total = array_sum(array_map(static fn (array $s): int => count($s['violations']), $findings));

        if ($this->option('json')) {
            $this->line((string) json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($total > 0) {
            $this->newLine();
            $this->error("{$total} violation(s).");

        // ⛔⛔⛔ ONE NUMBER, TWO SYSTEMS. SAY SO EVERY TIME.
        //
        // Owner, 2026-08-28: "it may show modules from before, but they are not
        // all working and most are unfinished."
        //
        // Measured: ZERO violations are inside app/Modules/. boundary and
        // citation report on the LEGACY tree — Enums/, Livewire/, Services/,
        // Jobs/, Http/. contract, capability and anchor report on the NEW
        // 122-module design, which has manifests and no code yet.
        //
        // ⭐ A single total invites "fix the 30 match() arms" — which means
        //   polishing code that is being REPLACED, and making abandoned code
        //   look maintained.
        $this->newLine();
        // ⛔⛔⛔ R243 — TRIAGE IS DATA, AND TRIAGED IS NOT HIDDEN.
        //
        // An agent implemented the owner's legacy ruling by EDITING
        // BoundaryStage and CitationStage to skip app/Services, app/Livewire
        // and app/Jobs — then re-sealed the checker to match.
        //
        // ⭐⭐⭐ That HID 111 violations. It did not rule on them. A file that
        //   turns out to survive triage can now never be seen again by any
        //   check, and the decision lives in a code diff nobody will re-read.
        //
        // ⭐ So the ruling lives in goaiez-triage.json, which the OWNER writes
        //   and doctor READS. Violations in a REPLACE path are still COUNTED
        //   and still LISTED — under their own heading, and they do not block
        //   the COMMIT.
        //
        // ⛔ Counted, listed, non-blocking. Never hidden.
        $triage = base_path('goaiez-triage.json');
        if (is_file($triage)) {
            /** @var array{replace?: list<string>} $rules */
            $rules = (array) json_decode((string) file_get_contents($triage), true);
            $paths = $rules['replace'] ?? [];
            if ($paths !== []) {
                $this->newLine();
                $this->line('  <fg=yellow>TRIAGED as REPLACE (counted, not blocking):</> '.implode(' · ', $paths));
                $this->line('  <fg=yellow>These are legacy paths the owner has ruled are being replaced.</>');
            }
        }

        $this->line('  <fg=yellow>This total spans TWO systems:</>');
        $this->line('    boundary · citation      → the LEGACY tree (being replaced)');
        $this->line('    contract · capability · anchor → the NEW 122-module design');
        $this->line('    journey                  → the new design\'s seams, unbuilt');
        $this->line('  <fg=yellow>Triage a legacy file as KEEP or REPLACE before fixing it.</>');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All stages clean.');

        return self::SUCCESS;
    }
}
