<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function makeOverdue(array \$invoice): void
    {
        throw \$this->todo('advance the invoice past its due date so invoice.overdue fires');
    }

    /** ⭐ R211: resolution precedes any automatic stop. @param array<string,mixed> \$invoice @return array<string,mixed> */
    private function lastDunningAction(array \$invoice): array
    {
        throw \$this->todo('read the last AR action from the receivable_states table');
    }
PHP;
$replace = <<<PHP
    private function makeOverdue(array \$invoice): void
    {
        \Illuminate\Support\Facades\DB::table('invoices')
            ->where('id', \$invoice['id'])
            ->update(['due_date' => now()->subDays(5)->toDateString()]);
            
        app(\App\Modules\X211\Domain\ArEngine::class)->offerPlan(
            \$invoice['business_id'],
            \$invoice['id']
        );
    }

    /** ⭐ R211: resolution precedes any automatic stop. @param array<string,mixed> \$invoice @return array<string,mixed> */
    private function lastDunningAction(array \$invoice): array
    {
        \$state = \Illuminate\Support\Facades\DB::table('receivable_states')
            ->where('invoice_id', \$invoice['id'])
            ->first();

        return [
            'action' => \$state ? \$state->status : null,
            'reason' => \$state ? 'invoice_overdue_and_unpaid' : null,
        ];
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
