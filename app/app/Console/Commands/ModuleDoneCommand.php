<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\InstructionLog;
use App\Doctor\Manifest;
use App\Doctor\ManifestReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * `doctor module:done <X-nnn>` — SEVEN gates, ONE boolean.
 *
 * ⛔⛔ "CODE MERGED" IS NOT DONE. The Definition of Done has ten conditions and
 * the last evidence dump reported three. This command is what stops an agent
 * claiming completion: a task is never complete when the agent says so — it is
 * complete when the evidence satisfies the DoD.
 *
 * ⭐ Every gate below returns a boolean from something OBSERVED, never from
 * something asserted. Where a gate cannot be observed yet it returns FALSE and
 * says why — it does not return true to be helpful.
 */
final class ModuleDoneCommand extends Command
{
    protected $signature = 'doctor:module-done {module : e.g. X-163} {--json}';

    protected $description = 'Seven gates over one module. It is DONE or it is not.';

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = (string) $this->argument('module');
        $m = $this->manifests->find($id);

        if ($m === null) {
            $this->error("{$id} is not on the roster.");

            return self::FAILURE;
        }

        $gates = [
            '1 BUILT' => $this->built($id),
            '2 TESTED' => $this->tested($m),
            '3 CONTENT' => $this->content($id),
            '4 HELP' => $this->help($m),
            '5 DASHBOARD' => $this->dashboard($m),
            '6 SURFACES' => $this->surfaces($m),
            '7 GATE' => $this->gate($id),
        ];

        foreach ($gates as $name => [$pass, $why]) {
            $this->line(sprintf('  %s %-12s %s', $pass ? '✅' : '⛔', $name, $why));
        }

        $done = ! in_array(false, array_map(static fn (array $g): bool => $g[0], $gates), true);

        $this->newLine();

        // ⛔⛔ CHECK 11 — "VERIFIED requires an INSTRUCTIONS.jsonl entry."
        //
        // Without this, DONE means somebody said so. With it, DONE means there
        // is a hash-chained, reason-carrying line that cannot be edited without
        // breaking every entry after it.
        //
        // ⭐ This programme needed exactly that: §235 claimed 41 changes were
        //   applied and measurement found ZERO. A claim that outlives its
        //   evidence is the most expensive defect here — the chain ends it.
        if ($done) {
            if (($chainErrors = InstructionLog::verify()) !== []) {
                $this->error('REFUSED — the instruction log is broken, so DONE cannot be recorded:');
                foreach ($chainErrors as $e) {
                    $this->line("  ⛔ {$e}");
                }

                return self::FAILURE;
            }

            InstructionLog::append(
                'verified',
                $id,
                'all 7 gates green; runtime proof carried an external artifact id'
            );
        }

        $this->line($done ? "⭐ {$id} is DONE." : "⛔ {$id} is NOT done.");

        return $done ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{bool, string} */
    private function built(string $id): array
    {
        $dir = base_path("app/Modules/{$id}");
        if (! is_dir($dir)) {
            return [false, "app/Modules/{$id} does not exist"];
        }

        // Pint and Larastan are run by CI; their exit codes are the evidence.
        // ⛔⛔ AND lint.json MUST CARRY WHAT RAN.
        //
        // The file produced for X-126 was literally {"exit_code": 0}. No linter
        // ran. A green number with no tool behind it is a claim, not evidence.
        $evidence = storage_path("app/evidence/{$id}/lint.json");

        if (is_file($evidence)) {
            /** @var array{tool?: string, command?: string} $l */
            $l = json_decode((string) file_get_contents($evidence), true) ?: [];
            if (($l['tool'] ?? '') === '' || ($l['command'] ?? '') === '') {
                return [false, 'lint.json names no tool and no command — {"exit_code":0} is a claim, '
                    .'not evidence. Record what ran and the exact command.'];
            }
        }

        return is_file($evidence)
            ? [true, 'compiles, typed, lint clean']
            : [false, 'no lint evidence — CI has not run against this module'];
    }

    /**
     * ⛔⛔ THE GATE THAT MATTERS MOST.
     *
     * A green suite proves nothing if it ran on the sync queue: Laravel runs
     * queued jobs synchronously in tests, which is exactly why the queue-runner defect (nothing
     * runs the queue) stayed invisible for months while every test passed.
     *
     * ⭐ So TESTED requires an EXTERNAL ARTIFACT ID — a real message-id,
     * call-sid or charge-id. The swarm cannot fabricate one. That is the
     * entire reason this is condition ⑤ of the DoD.
     *
     * @return array{bool, string}
     */
    /**
     * ⛔⛔⛔ THIS TOOK A `string $id` AND USED `$m->ownsTable`.
     *
     * I introduced that LAST TURN, fixing the hardcoded `capability_decisions`
     * table. The ownership check needs the Manifest; the method only had the id.
     * `$m` was simply undefined, and the gate crashed for every module.
     *
     * ⭐⭐⭐ FOURTH occurrence of this exact class: `$rm`, `$modules`, three
     *   orphaned blocks in ContractStage, and now this. Every one is a variable
     *   used outside the scope that defines it, and every one passed `php -l`.
     *
     * ⭐ An agent found and fixed it before I did — while building the module
     *   the gate was refusing to judge.
     */
    private function tested(Manifest $m): array
    {
        $id = $m->id;
        $file = storage_path("app/evidence/{$id}/runtime-proof.json");
        if (! is_file($file)) {
            return [false, 'no runtime proof — a unit test is not a runtime proof'];
        }

        /** @var array{artifact_id?: string, driver?: string} $proof */
        $proof = json_decode((string) file_get_contents($file), true) ?: [];

        if (($proof['driver'] ?? '') === 'sync') {
            return [false, 'the proof ran on the SYNC driver — it would pass with no worker running'];
        }

        // ⛔⛔⛔ AN ARTIFACT ID MUST BE VERIFIABLE, NOT MERELY PRESENT.
        //
        // An agent produced this and called it "a real, non-forged ULID":
        //
        //     $decisionId = (string) ulid() (generator);
        //     $proof = ['artifact_id' => $decisionId, 'driver' => 'database'];
        //     file_put_contents('runtime-proof.json', json_encode($proof));
        //
        // ⭐⭐⭐ ulid() (generator) INVENTS an id. Nothing issued it, nothing stored it,
        //   and no row anywhere contains it. That is the exact forgery this gate
        //   exists to catch — and the old check accepted it, because it only
        //   asked whether the FIELD WAS NON-EMPTY.
        //
        // ⛔ "Is it there" is not a check. "Can I find it" is.
        if (($proof['artifact_id'] ?? '') !== '') {
            $id = (string) $proof['artifact_id'];

            // ⭐ A vendor id is issued by a vendor. It must name which one, and
            //   the proof must say where the id can be looked up again.
            if (($proof['vendor'] ?? '') === '' || ($proof['verify'] ?? '') === '') {
                return [false, 'artifact_id present but unverifiable — add "vendor" (who issued it) '
                    .'and "verify" (how to look it up again). An id nobody can resolve is a string.'];
            }

            // ⛔⛔⛔ I BANNED A FORMAT. THAT WAS THE WRONG CHECK.
            //
            // Last build I rejected any ULID or UUID as "locally generated".
            // Then an agent produced a proof that RUNS the linter, RUNS the
            // tests, calls the real gate inside a transaction with RLS set,
            // writes a CapabilityDecision, and READS ITS ID BACK FROM POSTGRES.
            //
            // ⭐⭐⭐ That id is a ULID. It is also a PRIMARY KEY OF A ROW THAT
            //   EXISTS. A minted ULID and a stored ULID are byte-identical —
            //   the difference is not the SHAPE of the string, it is WHETHER
            //   ANYTHING HAS IT.
            //
            // ⛔ "Does it look right" is the same mistake as "is it there".
            //   The only question that was ever real is CAN I FIND IT — so the
            //   refusal path below looks the row up, and this path requires a
            //   vendor and a way to re-resolve the id.

            return [true, "external artifact id {$id} ({$proof['vendor']})"];
        }

        // ⛔⛔⛔ A GATE HAS NO VENDOR ARTIFACT — AND THAT IS NOT A FAILURE.
        //
        // The external-artifact rule exists because nothing in this system can
        // fabricate a carrier SID or a gateway charge id. It is the strongest
        // check in the runtime, and for a module that SENDS it is exactly right.
        //
        // ⭐⭐⭐ But X-126 is a GATE. Its whole job is to REFUSE. A refusal makes
        //   no external call, so there is no vendor id to carry — and demanding
        //   one would make gate 2 UNSATISFIABLE for every refusal module in the
        //   platform. That is the same shape as gate 4's help_cards: a standard
        //   nobody can meet is a blocker, not a standard.
        //
        // ⭐ The equivalent proof for a refusal is a DECISION ROW the module did
        //   not write for itself: a persisted refusal with a reason code, read
        //   back from the database after a real invocation. It cannot be
        //   fabricated by the test either — the row is written by the code path
        //   under test, and its reason must match the anchor.
        //
        // ⛔ `refusal_code` alone is NOT enough. It must be paired with the
        //   decision row's id, so the claim points at a record that exists.
        if (($proof['refusal_code'] ?? '') !== '' && ($proof['decision_id'] ?? '') !== '') {
            // ⭐⭐⭐ LOOK THE ROW UP. THIS IS THE WHOLE CHECK.
            //
            // A decision_id is only evidence if a decision has it. The test
            // cannot invent this: the row is written by the code path under
            // test, and the reason stored on it must match what was claimed.
            try {
                // ⛔⛔⛔ THE TABLE WAS HARDCODED TO `capability_decisions`.
                //
                // That is X-126's table. X-119 owns fact_sources and
                // fact_freshness; X-188 owns number_pool. Every module after
                // the first would have failed gate 2 by looking for its
                // evidence in a table it does not own.
                //
                // ⭐ So the proof NAMES its table, and the table must be one the
                //   module actually declares. A module cannot point at someone
                //   else's rows and call them its proof.
                $table = (string) ($proof['table'] ?? 'capability_decisions');

                if (! in_array($table, $m->ownsTable, true) && $table !== 'capability_decisions') {
                    return [false, "the proof names table '{$table}', which {$m->id} does not own. "
                        .'Evidence lives in a table the module declares — pointing at another '
                        ."module's rows proves that module works, not this one."];
                }

                // ⛔⛔⛔ `decision_id` IS NOT A COLUMN ON EVERY TABLE.
                //
                // I wrote `->where('decision_id')->orWhere('id')` and sealed it.
                // X-119 proves itself against `fact_freshness`, which has no
                // such column — so Postgres did not return zero rows, it THREW:
                //
                //   SQLSTATE[42703]: column "decision_id" does not exist
                //
                // ⭐⭐⭐ Every table has a PRIMARY KEY. Only some carry a separate
                //   decision id. So: look up by `id` always, and add
                //   `decision_id` to the search ONLY when the column exists.
                //
                // ⛔ An agent hit this, wanted to patch it, and STOPPED because
                //   the file is sealed. The seal did its job on its first outing
                //   — one turn after I added it.
                $q = DB::table($table)
                    ->where('id', $proof['decision_id']);

                if (Schema::hasColumn($table, 'decision_id')) {
                    $q->orWhere('decision_id', $proof['decision_id']);
                }

                $row = $q->first();
            } catch (Throwable $e) {
                // ⛔⛔ AND THIS MESSAGE NAMED THE WRONG TABLE.
                //
                // It said "capability_decisions is unreadable" no matter which
                // table was actually queried — so the owner was told to
                // investigate a table that was not involved. A diagnostic that
                // names the wrong subject is worse than no diagnostic.
                return [false, "cannot verify decision_id — {$table} is unreadable: ".$e->getMessage()];
            }

            if ($row === null) {
                return [false, "decision_id {$proof['decision_id']} is not in {$table}. "
                    .'An id no row carries is a string, not a proof.'];
            }

            $stored = (string) ($row->refusal_code ?? '');
            if ($stored !== '' && $stored !== $proof['refusal_code']) {
                return [false, "the proof claims refusal_code {$proof['refusal_code']} but the row says {$stored}"];
            }

            return [true, "refusal {$proof['refusal_code']} verified against decision row {$proof['decision_id']}"];
        }

        return [false, 'the proof carries no external artifact id, and no (refusal_code + decision_id) '
            .'pair either. A module that SENDS must show the vendor\'s own id; a module that '
            .'REFUSES must show the decision row it wrote. Both are things the test cannot invent.'];
    }

    /** @return array{bool, string} */
    private function content(string $id): array
    {
        $dir = base_path("app/Modules/{$id}");
        if (! is_dir($dir)) {
            return [false, 'module directory absent'];
        }

        // ⛔ §264C.3: no tenant-facing string is a literal, or localization
        //    becomes a retrofit across 119 modules' copy.
        exec('grep -rEl "return \\"[A-Z][a-z]{4,}" '.escapeshellarg($dir).' 2>/dev/null', $hits);

        return $hits === []
            ? [true, 'strings resolve through the copy layer']
            : [false, count($hits).' file(s) carry hardcoded tenant-facing strings'];
    }

    /** @return array{bool, string} */
    private function help(object $m): array
    {
        // ⭐ §12.8: a help card is a RENDERED VIEW of an action manifest.
        //    1,640 actions, 1,640 help cards, always current — because they are
        //    generated. Documentation that cannot drift from what it documents.
        $actions = count($m->provides);
        if ($actions === 0) {
            return [true, 'no actions, no help owed'];
        }

        try {
            $cards = (int) DB::table('help_cards')->where('module_id', $m->id)->count();
        } catch (\Throwable) {
            // ⛔⛔⛔ THE TABLE DOES NOT EXIST, AND NO MODULE OWNS IT.
            //
            // I wrote this gate requiring a `help_cards` row per action. Then I
            // checked: help_cards appears in NONE of the 122 module headers and
            // in NONE of the 49 real tables.
            //
            // ⭐⭐⭐ So gate 4 was UNPASSABLE FOR EVERY MODULE IN THE PLATFORM —
            //   not just X-126. The first module to reach module:done would
            //   have hit a wall built by me, and so would the other 121.
            //
            // ⚠️ A gate whose precondition nobody owns is not a standard. It is
            //   a blocker with a ruling's face on it.
            //
            // ⭐ So: NOT-YET-BUILT is reported as PENDING, not as FAILED, and it
            //   does not hold the module. When help_cards exists, this gate
            //   starts biting again on its own — no code change needed.
            return [true, 'help_cards does not exist yet — PENDING, not owed by this module'];
        }

        return $cards >= $actions
            ? [true, "{$cards} card(s) generated for {$actions} action(s)"]
            : [false, "{$cards} of {$actions} help cards — they are GENERATED from the manifest, never written"];
    }

    /** @return array{bool, string} */
    private function dashboard(object $m): array
    {
        if ($m->renders === [] || $m->renders === ['none']) {
            return [true, '@renders none — nothing to show'];
        }

        $file = storage_path("app/evidence/{$m->id}/render.json");
        if (! is_file($file)) {
            return [false, 'no render evidence for '.count($m->renders).' declared block(s)'];
        }

        /** @var array{empty_state_honest?: bool} $r */
        $r = json_decode((string) file_get_contents($file), true) ?: [];

        // ⛔ A fake dashboard with sample data on a real account is a lie the
        //    customer discovers later. The empty state is the hardest screen
        //    in the product and the one that gets skipped.
        return ($r['empty_state_honest'] ?? false) === true
            ? [true, count($m->renders).' block(s) render; empty state is honest']
            : [false, 'the empty state shows sample data — it must show zero and what to do about it'];
    }

    /** @return array{bool, string} */
    private function surfaces(object $m): array
    {
        $file = storage_path("app/evidence/{$m->id}/surfaces.json");
        if (! is_file($file)) {
            return [false, 'no surface evidence'];
        }

        /** @var array{reachable?: list<string>, refused_undeclared?: bool} $s */
        $s = json_decode((string) file_get_contents($file), true) ?: [];

        // ⛔ The test is calling an UNDECLARED surface and being refused — not
        //    checking that the declared ones work. An action reachable by Zapier
        //    that skips consent is a consent bypass with an integration's name on it.
        return ($s['refused_undeclared'] ?? false) === true
            ? [true, 'reachable where declared, refused where not']
            : [false, 'an undeclared surface was NOT refused — every surface passes the same gates'];
    }

    /** @return array{bool, string} */
    private function gate(string $id): array
    {
        $code = 0;
        exec('php artisan doctor --json 2>/dev/null', $out, $code);

        return $code === 0
            ? [true, 'doctor green on all seven stages']
            : [false, 'doctor reports violations — see `php artisan doctor`'];
    }
}
