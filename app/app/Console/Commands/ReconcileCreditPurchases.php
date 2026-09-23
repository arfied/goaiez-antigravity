<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PurchaseReconciliationVerdict;
use App\Models\CreditPurchaseReconciliation;
use App\Services\Billing\PurchaseReconciliation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The scheduled reader for a payment whose notification never arrived (3483).
 *
 * ⛔ **WITHOUT THIS, A TENANT WHO HAS PAID AND HAS NO CREDIT NEEDS A HUMAN TO
 * NOTICE.** Authorize.Net publishes no webhook retry schedule, so an `authorized`
 * purchase whose notification was lost stays that way for ever; on Stripe the
 * equivalent row sits at `pending`, looking exactly like a checkout somebody
 * closed. {@see PurchaseReconciliation} carries the whole argument — what counts
 * as paid on each vendor, why nothing here may write to a purchase row directly,
 * and how the sweep is bounded.
 *
 * ⚠️ **THE COMMAND IS A THIN SHELL ON PURPOSE.** Everything that decides whether
 * somebody's balance moves is in the service, where it is driven directly by
 * tests; a `handle()` that made any of those decisions would be a second place to
 * change them.
 *
 * ⚠️ **`--limit` BOUNDS THE VENDOR CALLS AND NOT THE ROWS SCANNED.** A backlog
 * means something is already wrong with a webhook endpoint, and the answer to that
 * is not a thousand gateway reads in one minute — the next run takes the next
 * hundred, oldest first, so nobody is starved.
 */
#[Signature('billing:reconcile-purchases {--limit= : How many gateway reads this run}')]
#[Description('Ask each gateway about purchases where money moved and credit did not')]
final class ReconcileCreditPurchases extends Command
{
    public function handle(PurchaseReconciliation $reconciliation): int
    {
        $limit = $this->limit();

        if ($limit === null) {
            $this->error('--limit must be a whole number of gateway reads, at least 1.');

            return self::FAILURE;
        }

        $written = $reconciliation->sweep($limit);

        if ($written === []) {
            $this->info('No purchase needed reconciling.');

            return self::SUCCESS;
        }

        foreach ($this->tally($written) as $verdict => $count) {
            $this->line("{$verdict}: {$count}");
        }

        // ⚠️ THE TWO WORTH SAYING OUT LOUD, AND THEY ARE THE TWO A PERSON HAS TO
        // ACT ON. A credit written here means a notification was lost; an
        // abandonment means a payment nobody has explained. Both are `Log::warning`
        // inside the service as well, because a scheduled run's console output is
        // read by nobody.
        $credited = $this->countOf($written, PurchaseReconciliationVerdict::Credited);
        $abandoned = $this->countOf($written, PurchaseReconciliationVerdict::Abandoned);

        if ($credited > 0) {
            $this->warn("{$credited} purchase(s) were credited from a gateway read rather than a notification.");
        }

        if ($abandoned > 0) {
            $this->warn("{$abandoned} purchase(s) were given up on and need a person.");
        }

        return self::SUCCESS;
    }

    /**
     * Null means the option was given and is not usable.
     *
     * ⚠️ **REFUSED RATHER THAN CLAMPED.** A `--limit=0` that silently became the
     * default would be a run that did nothing while reporting success, and a
     * negative one is a typo somebody should see.
     */
    private function limit(): ?int
    {
        $raw = $this->option('limit');

        if ($raw === null) {
            return app(PurchaseReconciliation::class)->defaultBatch();
        }

        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @param  list<CreditPurchaseReconciliation>  $written
     * @return array<string, int>
     */
    private function tally(array $written): array
    {
        $tally = [];

        foreach ($written as $row) {
            $tally[$row->verdict->value] = ($tally[$row->verdict->value] ?? 0) + 1;
        }

        ksort($tally);

        return $tally;
    }

    /**
     * @param  list<CreditPurchaseReconciliation>  $written
     */
    private function countOf(array $written, PurchaseReconciliationVerdict $verdict): int
    {
        return count(array_filter(
            $written,
            fn (CreditPurchaseReconciliation $row): bool => $row->verdict === $verdict,
        ));
    }
}
