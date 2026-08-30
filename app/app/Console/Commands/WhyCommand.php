<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\ManifestReader;
use Illuminate\Console\Command;

/**
 * `why <module|rule>` — why is this the way it is?
 *
 * ⛔⛔ THE PROBLEM THIS SOLVES, AND IT IS THE MOST EXPENSIVE ONE IN THE PROGRAMME:
 *
 * A constraint whose REASON is lost gets removed by the next competent person,
 * because it looks arbitrary. This programme watched it happen repeatedly —
 * a P-163 violation was found, written down, and never fixed; §235 classified
 * 37 orphans and a later line claimed 41 changes were applied when ZERO were;
 * X-168 carried a struck capability through eight gates.
 *
 * ⭐⭐⭐ Every one of those survived because the WHY lived somewhere other than
 * the thing it governed. `why` puts them back together.
 *
 * ⛔ It answers from the LAW and the DECLARATION, never from prose. If no reason
 * is recorded, it says so plainly rather than inventing a plausible one — an
 * invented rationale is worse than a missing one, because it ends the enquiry.
 */
final class WhyCommand extends Command
{
    protected $signature = 'why {subject : a module id, or a rule id like P-163 / R230 / N-232-01}';

    protected $description = 'The reason a thing is the way it is — from law and declarations, never prose.';

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $subject = (string) $this->argument('subject');

        if (preg_match('/^(P|R|N|Q|M)-?\d/i', $subject) === 1 || str_starts_with($subject, 'LAW')) {
            return $this->rule($subject);
        }

        return $this->module($subject);
    }

    private function rule(string $id): int
    {
        $plan = base_path('GOAIEZ-MASTER-PLAN.md');
        if (! is_file($plan)) {
            $this->error('No plan file — cannot resolve a rule id.');

            return self::FAILURE;
        }

        $text = (string) file_get_contents($plan);
        $e = preg_quote($id, '/');

        // ⭐ A DEFINITION POSITION, not a mention. This is the CitationStage
        //   lesson: `M-95` was cited four times and defined zero, and three of
        //   those citations were the author quoting themselves. A mention
        //   proves nothing.
        $patterns = [
            '/\|\s*\*{0,2}'.$e.'\*{0,2}\s*\|([^|]{10,400})\|/u',
            '/\*{0,2}'.$e.'\*{0,2}\s*[—:-]\s*([^\n]{10,400})/u',
            '/\*{0,2}'.$e.'\*{0,2}\s+(\*{0,2}[A-Z][^\n]{10,400})/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m) === 1) {
                $this->line("# WHY — {$id}");
                $this->newLine();
                $this->line('  '.trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1]))));
                $this->newLine();
                $this->line('  ⭐ Read from a DEFINITION position, not a mention.');

                return self::SUCCESS;
            }
        }

        $mentions = substr_count($text, $id);
        $this->error("REFUSED — {$id} is mentioned {$mentions} time(s) but never DEFINED.");
        $this->line('  ⛔ An id that cannot be looked up is worse than no citation: it looks');
        $this->line('     authoritative and ends the argument. M-95 was cited 4 times and');
        $this->line('     defined 0 — three of those were the author quoting themselves.');

        return self::FAILURE;
    }

    private function module(string $id): int
    {
        foreach ($this->manifests->all() as $m) {
            if ($m->id !== $id) {
                continue;
            }

            $this->line("# WHY — {$m->id}");
            $this->newLine();
            $this->line("  It exists to: {$m->intent}");
            $this->line("  It ships at {$m->shipsAt} — its CEILING — because R235 says every");
            $this->line('  autopilot ships ON. The client turns it off if they want.');
            $this->newLine();

            if ($m->ownsTable !== []) {
                $this->line('  It owns '.implode(' · ', $m->ownsTable).' — and P-163 means');
                $this->line('  no other module may keep its own copy of those.');
            }

            if (in_array('*', $m->consumes, true)) {
                $this->newLine();
                $this->line('  ⭐ It consumes the WILDCARD because it is a SPINE module: it handles');
                $this->line('     every event by nature. Legal for the spine only, and DECLARED —');
                $this->line('     written as prose ("everything — it is the transport") it parsed as');
                $this->line('     EMPTY, and this module read as consuming nothing.');
            }

            return self::SUCCESS;
        }

        $this->error("No module with the exact id '{$id}'.");

        return self::FAILURE;
    }
}
