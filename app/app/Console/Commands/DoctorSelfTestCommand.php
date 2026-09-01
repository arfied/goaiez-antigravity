<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

/**
 * ⭐⭐⭐⭐ THE COMMAND THAT ENDS THE ROUND-TRIP LOOP.
 *
 * WHY THIS EXISTS:
 *
 * For thirty turns the cycle was: I ship a file → it runs for the first time on
 * the owner's machine → it breaks → he screenshots the error → I fix it → repeat.
 * `$rm` undefined. `$modules` undefined. A `php -r` call in a container with no
 * PHP. Three checks sitting outside the loop that defines their variables.
 *
 * ⛔ Every one of those dies on FIRST EXECUTION. None of them survives reading.
 *
 * ⭐ So this runs MY OWN RUNTIME against YOUR tree and reports what is broken
 *   about the CHECKER — separately from what is broken about the CODE. Those
 *   are different questions and mixing them cost this project weeks.
 *
 * ⛔⛔ It is not part of `doctor`. `doctor` audits the platform; this audits the
 *   thing that audits the platform, and the two must never share a number.
 */
final class DoctorSelfTestCommand extends Command
{
    protected $signature = 'doctor:selftest {--json : machine-readable output}';

    protected $description = 'Verify the GOAIEZ runtime itself — syntax, execution, and strict-mode warnings';

    public function handle(): int
    {
        $results = [];

        // ① SYNTAX — a file that will not parse cannot be debugged by its output.
        $bad = [];
        foreach ($this->runtimeFiles() as $f) {
            $out = [];
            $code = 0;
            exec('php -l '.escapeshellarg($f).' 2>&1', $out, $code);
            if ($code !== 0) {
                $bad[] = basename($f).': '.trim((string) ($out[1] ?? $out[0] ?? 'parse error'));
            }
        }
        $results['syntax'] = ['pass' => $bad === [], 'detail' => $bad];

        // ② EXECUTION UNDER STRICT MODE — every warning becomes a fatal.
        //
        // ⭐⭐⭐ THIS IS THE ONE THAT MATTERS. `Undefined variable $m` is a
        //   WARNING in PHP: the stage keeps running, silently reading null, and
        //   reports a number that means nothing. Three checks in ContractStage
        //   did exactly that — including N-235-04, the one supervising all 102
        //   autopilots. It had never executed.
        $stages = [];
        foreach ($this->stageClasses() as $name => $class) {
            $prev = set_error_handler(static function (int $n, string $s, string $f, int $l): bool {
                throw new \ErrorException($s, 0, $n, $f, $l);
            });

            try {
                $stage = app($class);
                $violations = $stage->run();
                $stages[$name] = ['ok' => true, 'violations' => count($violations)];
            } catch (Throwable $e) {
                $stages[$name] = [
                    'ok' => false,
                    'error' => get_class($e).': '.$e->getMessage(),
                    'at' => basename($e->getFile()).':'.$e->getLine(),
                ];
            } finally {
                set_error_handler($prev);
            }
        }
        $results['stages'] = $stages;

        // ③ THE SEALS — is the checker the one that was shipped?
        $seal = base_path('app/Doctor/seals.json');
        $tampered = [];
        if (is_file($seal)) {
            /** @var array<string,string> $seals */
            $seals = (array) json_decode((string) file_get_contents($seal), true);
            foreach ($seals as $rel => $hash) {
                $p = base_path($rel);
                if (! is_file($p) || hash('sha256', (string) file_get_contents($p)) !== $hash) {
                    $tampered[] = $rel;
                }
            }
        }
        // ⛔⛔⛔ AND THE SEAL OF THE SEALS.
        //
        // An agent hit a seal mismatch — caused by Claude shipping two loose
        // .php files outside the bundle — and did the reasonable thing: it
        // recomputed the hashes and WROTE THEM INTO seals.json. selftest then
        // passed.
        //
        // ⭐⭐⭐ THE SEAL CERTIFIED ITSELF. On a machine the agent controls, no
        //   local check can be tamper-proof — the trust anchor has to live
        //   somewhere the agent cannot reach.
        //
        // ⭐ So: the DIGEST of seals.json is printed here, and the installer
        //   prints the same digest when it unpacks. THE OWNER COMPARES THEM.
        //   That moves the anchor off the machine and into a human's notes,
        //   which is the only place it was ever safe.
        //
        // ⛔ It still does not PREVENT anything. It makes a re-seal impossible
        //   to perform silently, which is all a seal was ever able to do.
        $results['seals'] = [
            'pass' => $tampered === [],
            'detail' => $tampered,
            'digest' => is_file($seal) ? substr(hash('sha256', (string) file_get_contents($seal)), 0, 16) : 'ABSENT',
        ];

        // ④ SPLIT MODULES — R242. A hyphenless twin means half the checks are blind.
        $split = [];
        foreach (glob(base_path('app/Modules/*'), GLOB_ONLYDIR) ?: [] as $d) {
            $n = basename($d);
            if (preg_match('/^([XC])([0-9]+)$/', $n, $h) === 1 && is_dir(base_path("app/Modules/{$h[1]}-{$h[2]}"))) {
                $split[] = $n.' ↔ '.$h[1].'-'.$h[2];
            }
        }
        $results['modules'] = ['pass' => $split === [], 'detail' => $split];

        if ($this->option('json')) {
            $this->line((string) json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $results['syntax']['pass'] && $results['seals']['pass'] ? self::SUCCESS : self::FAILURE;
        }

        return $this->render($results);
    }

    /** @param array<string,mixed> $r */
    private function render(array $r): int
    {
        $fail = 0;

        $this->newLine();
        $this->line('  <options=bold>GOAIEZ RUNTIME SELF-TEST</> · build '.DoctorCommand::BUILD);

        $this->line($r['syntax']['pass']
            ? '  <fg=green>✓</> syntax     every runtime file parses'
            : '  <fg=red>✗</> syntax     '.count($r['syntax']['detail']).' file(s) will not parse');
        foreach ($r['syntax']['detail'] as $d) {
            $this->line("      · {$d}");
            $fail++;
        }

        foreach ($r['stages'] as $name => $s) {
            if ($s['ok']) {
                $this->line(sprintf('  <fg=green>✓</> %-10s executes clean · %d violation(s)', $name, $s['violations']));

                continue;
            }
            $this->line(sprintf('  <fg=red>✗</> %-10s %s', $name, $s['error']));
            $this->line("      at {$s['at']}");
            $fail++;
        }

        $this->line('  <options=bold>seal digest</> '.$r['seals']['digest']
            .'   <fg=yellow>← must match what goaiez-runtime.sh printed at install</>');
        $this->line($r['seals']['pass']
            ? '  <fg=green>✓</> seals      every sealed file matches seals.json'
            : '  <fg=red>✗</> seals      MODIFIED: '.implode(', ', $r['seals']['detail']));
        $fail += $r['seals']['pass'] ? 0 : 1;

        $this->line($r['modules']['pass']
            ? '  <fg=green>✓</> modules    no split modules (R242)'
            : '  <fg=red>✗</> modules    SPLIT: '.implode(', ', $r['modules']['detail']));
        $fail += $r['modules']['pass'] ? 0 : 1;

        $this->newLine();
        if ($fail === 0) {
            $this->line('  <fg=green;options=bold>The runtime is sound. Any violation doctor reports is about YOUR CODE.</>');

            return self::SUCCESS;
        }

        $this->line("  <fg=red;options=bold>{$fail} problem(s) IN THE RUNTIME ITSELF.</>");
        $this->line('  <fg=yellow>Paste this output back. Do NOT patch app/Doctor — it is sealed,</>');
        $this->line('  <fg=yellow>and a checker you repaired yourself is a checker nobody reviewed.</>');

        return self::FAILURE;
    }

    /** @return list<string> */
    private function runtimeFiles(): array
    {
        $out = [];
        foreach (['app/Doctor', 'app/Console/Commands'] as $dir) {
            foreach (glob(base_path($dir.'/**/*.php')) ?: [] as $f) {
                $out[] = $f;
            }
            foreach (glob(base_path($dir.'/*.php')) ?: [] as $f) {
                $out[] = $f;
            }
        }

        return $out;
    }

    /** @return array<string,class-string> */
    private function stageClasses(): array
    {
        return [
            'integrity' => \App\Doctor\Stages\IntegrityStage::class,
            'boundary' => \App\Doctor\Stages\BoundaryStage::class,
            'contract' => \App\Doctor\Stages\ContractStage::class,
            'citation' => \App\Doctor\Stages\CitationStage::class,
            'capability' => \App\Doctor\Stages\CapabilityStage::class,
            'anchor' => \App\Doctor\Stages\TestAnchorStage::class,
        ];
    }
}
