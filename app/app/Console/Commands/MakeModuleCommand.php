<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\InstructionLog;
use App\Doctor\ManifestReader;
use Illuminate\Console\Command;

/**
 * `make:module <id>` — scaffold a module folder, or REFUSE.
 *
 * ⭐⭐⭐ N-264-05: "make:module REFUSES a header with fewer than eight fields;
 * these two are the only writers of scaffolding and briefs."
 *
 * ⛔⛔ AND IT TAKES A LOCK, WHICH IS NOT INCIDENTAL.
 *
 * The plan: "make:module takes a lock on BUILD-STATE.json, reads the ceiling,
 * writes." Three agents scaffolding in parallel — which wave 0 explicitly does —
 * will otherwise read the same ceiling and mint the SAME NUMBER three times.
 *
 * ⭐ This programme has already lived the un-locked version of this problem:
 * the roster held 122 modules while both trackers said 119, and three fully
 * specified modules were invisible to every tool. A ceiling read without a lock
 * is that defect waiting to happen in a single afternoon.
 */
final class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {id? : omit to take the next free number} {--dry-run}';

    protected $description = 'Scaffold a module. Locks the ceiling, refuses a header under eight fields.';

    /** ⛔ §157's eight. Fewer than these is not a module, it is a note. */
    private const REQUIRED_FIELDS = [
        '@intent', '@provides', '@emits', '@consumes',
        '@owns_table', '@renders', 'ships:', 'TEST ANCHOR',
    ];

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $lock = base_path('BUILD-STATE.json.lock');

        // ⛔⛔ THE LOCK COMES FIRST — before reading the ceiling, not after.
        //    Reading then locking is the same race with extra steps.
        $fh = fopen($lock, 'c');
        if ($fh === false || ! flock($fh, LOCK_EX | LOCK_NB)) {
            $this->error('REFUSED — another make:module holds the lock.');
            $this->line('  ⭐ Wait and retry. Three agents scaffolding in parallel without this');
            $this->line('     lock read the same ceiling and mint the SAME id three times.');

            return self::FAILURE;
        }

        try {
            $id = (string) ($this->argument('id') ?? $this->nextFree());

            foreach ($this->manifests->all() as $m) {
                if ($m->id === $id) {
                    $this->error("REFUSED — {$id} already exists.");
                    $this->line('  ⛔ Scaffolding over a real module is how a contract gets silently replaced.');

                    return self::FAILURE;
                }
            }

            if (($missing = $this->missingFields($id)) !== []) {
                $this->error("REFUSED — {$id}'s header has fewer than eight fields.");
                foreach ($missing as $f) {
                    $this->line("  ⛔ missing {$f}");
                }
                $this->newLine();
                $this->line('  N-264-05. A header short of eight fields produces a brief with holes,');
                $this->line('  and an agent fills a hole by inferring — which is how a module gets');
                $this->line('  built to a contract nobody wrote.');

                return self::FAILURE;
            }

            if ($this->option('dry-run')) {
                $this->info("Would scaffold {$id}.");

                return self::SUCCESS;
            }

            $this->scaffold($id);

            // ⭐ One line per action, append-only. `reason` is mandatory because
            //   a scaffold with no recorded reason is a module nobody can
            //   later justify — and an unjustified module gets deleted.
            InstructionLog::append('scaffold', $id, 'minted at the ceiling under lock; header passed the eight-field gate');

            $this->info("Scaffolded {$id} — status: BUILDING, scaffold: true.");

            return self::SUCCESS;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * ⛔ Reads the ceiling from the MANIFESTS, not from a tracker.
     *
     * A tracker is a claim; the manifests are the thing. This programme's
     * roster drifted by three because a count written down once was trusted
     * over a count taken.
     */
    private function nextFree(): string
    {
        $max = 0;
        foreach ($this->manifests->all() as $m) {
            if (preg_match('/^X-(\d{3})$/', $m->id, $mm) === 1) {
                $max = max($max, (int) $mm[1]);
            }
        }

        return 'X-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /** @return list<string> */
    private function missingFields(string $id): array
    {
        $plan = base_path('GOAIEZ-MASTER-PLAN.md');
        if (! is_file($plan)) {
            return self::REQUIRED_FIELDS;
        }

        $text = (string) file_get_contents($plan);
        if (preg_match('/@module\s+\*{0,2}'.preg_quote($id, '/').'\b(.{0,6500})/s', $text, $m) !== 1) {
            return self::REQUIRED_FIELDS;
        }

        return array_values(array_filter(
            self::REQUIRED_FIELDS,
            static fn (string $f): bool => ! str_contains($m[1], $f)
        ));
    }

    private function scaffold(string $id): void
    {
        $dir = base_path("app/Modules/{$id}");
        foreach (['', '/Actions', '/Tests'] as $sub) {
            if (! is_dir($dir.$sub)) {
                mkdir($dir.$sub, 0o755, true);
            }
        }

        // ⭐ An empty seeds.yml, deliberately: P-193 says every operational value
        //   is a ROW. A module with no seeds file grows hardcoded values.
        if (! is_file("{$dir}/seeds.yml")) {
            file_put_contents("{$dir}/seeds.yml", "# {$id} — operational values live here as ROWS (P-193), never in code.\n");
        }

        $state = base_path('BUILD-STATE.json');
        $json = is_file($state) ? (array) json_decode((string) file_get_contents($state), true) : [];
        $json[$id] = ['status' => 'BUILDING', 'scaffold' => true];
        file_put_contents($state, (string) json_encode($json, JSON_PRETTY_PRINT));
    }
}
