<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Doctor\ManifestReader;
use Illuminate\Console\Command;

/**
 * `find <term>` — where does this actually live?
 *
 * ⭐⭐⭐ N-263-04 IS THE WHOLE DESIGN:
 *
 *     "find searches DECLARATIONS and code, NEVER PROSE — the plan describes
 *      things never built and things later killed."
 *
 * ⛔⛔ A 3 MB plan contains rejected proposals, superseded designs, struck
 * features and long arguments about things that do not exist. This programme
 * has the receipts: `Payroll Export` survived in prose long after P-204 fenced
 * payroll; a shoe-shop example about Miami inventory kept reappearing in a
 * product for plumbers; `X-168` advertised "overtime math" for an entire
 * session after it was struck.
 *
 * ⭐ So searching prose returns things that were never built, things that were
 * killed, and things somebody argued for once. Searching DECLARATIONS returns
 * what the system actually is.
 */
final class FindCommand extends Command
{
    protected $signature = 'find {term} {--prose : also search the plan, clearly marked as NOT the system}';

    protected $description = 'Search declarations and code. Never prose — N-263-04.';

    public function __construct(private readonly ManifestReader $manifests)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $term = strtolower((string) $this->argument('term'));
        $hits = 0;

        $this->line("# FIND — {$term}");
        $this->newLine();
        $this->line('## DECLARED (this is what the system IS)');

        foreach ($this->manifests->all() as $m) {
            $where = [];
            foreach ([
                'provides' => $m->provides,
                'emits' => $m->emits,
                'consumes' => $m->consumes,
                'owns_table' => $m->ownsTable,
                'reads_table' => $m->readsTable,
                'renders' => $m->renders,
            ] as $field => $values) {
                foreach ($values as $v) {
                    if (str_contains(strtolower($v), $term)) {
                        $where[] = "@{$field} {$v}";
                    }
                }
            }

            if ($where !== []) {
                $hits++;
                $this->line("  {$m->id}  ".implode('  ·  ', $where));
            }
        }

        if ($hits === 0) {
            $this->line('  nothing declared.');
            $this->newLine();
            // ⭐ The useful half of a null result: a term that appears in the plan
            //   but in no declaration is either unbuilt or struck, and saying so
            //   is more valuable than a silent "no results".
            $this->line('  ⛔ If this term appears in the plan but nowhere above, it was either');
            $this->line('     never built or later struck. The plan argues; the declarations decide.');
        }

        $this->newLine();
        $this->line("— {$hits} module(s) declare it");

        if ($this->option('prose')) {
            $this->newLine();
            $this->line('## IN THE PLAN (⛔ NOT evidence that anything exists)');
            $plan = base_path('GOAIEZ-MASTER-PLAN.md');
            if (is_file($plan)) {
                $n = substr_count(strtolower((string) file_get_contents($plan)), $term);
                $this->line("  {$n} mention(s) — including rejected proposals, superseded designs and struck features.");
            }
        }

        return self::SUCCESS;
    }
}
