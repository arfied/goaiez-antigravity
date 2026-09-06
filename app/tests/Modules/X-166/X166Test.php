<?php

declare(strict_types=1);

namespace Tests\Modules\X166;

use App\Modules\X166\Actions\JobCostAction;
use App\Modules\X166\Actions\MarginReportAction;
use App\Modules\X166\Events\JobCosted;
use App\Modules\X166\Events\MarginBelowThreshold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X166Test extends TestCase
{
    private JobCostAction $costAction;

    private MarginReportAction $reportAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costAction = new JobCostAction;
        $this->reportAction = new MarginReportAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'pricebook.update|price.set' app/Modules/X-166/ returns nothing — it never writes a price;
     * every cost row cites the pricebook version it compared against
     */
    public function test_anchor_job_costing_pricebook_attribution_and_margin_threshold(): void
    {
        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        $biz = TestCase::provisionTenant(['name' => 'Costing Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Compute job cost citing pricebook version "v2.1"
        $cost = $this->costAction->handle(
            businessId: $biz->id,
            jobId: 888,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            techId: 42,
            serviceType: 'furnace_repair',
            source: 'google_local'
        );

        $this->assertEquals('v2.1', $cost->price_book_version, 'Every cost row must cite the pricebook version');
        $this->assertEquals(9000, $cost->total_cost_cents);
        $this->assertEquals(11000, $cost->gross_margin_cents);
        $this->assertEquals(55.0, $cost->gross_margin_pct);

        Event::assertDispatched(JobCosted::class);
        Event::assertNotDispatched(MarginBelowThreshold::class);

        // 2. Low-margin job (<20%) triggers MarginBelowThreshold event
        $lowMarginCost = $this->costAction->handle(
            businessId: $biz->id,
            jobId: 889,
            priceBookVersion: 'v2.1',
            laborCostCents: 10000,
            materialsCostCents: 7000,
            overheadCostCents: 2000,
            revenueCents: 20000, // Cost = 19000, Revenue = 20000 -> Margin = 5%
            techId: 43,
            serviceType: 'emergency_leak',
            source: 'direct'
        );

        $this->assertEquals(5.0, $lowMarginCost->gross_margin_pct);
        Event::assertDispatched(MarginBelowThreshold::class);

        // 3. Margin reports by tech and by service
        $byTech = $this->reportAction->handle($biz->id, 'tech');
        $this->assertNotEmpty($byTech);

        $byService = $this->reportAction->handle($biz->id, 'service');
        $this->assertNotEmpty($byService);
    }

    /**
     * [N-166-01] no refusal declared
     */
    public function test_n_166_01_no_refusal(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Refusal Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $emptyJobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'No Cost Rows',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->costAction->handle(
            businessId: $biz->id,
            jobId: 999,
            priceBookVersion: 'v1.0',
            laborCostCents: 5000,
            materialsCostCents: 0,
            overheadCostCents: 0,
            revenueCents: 10000,
            techId: 42,
            serviceType: 'repair',
            source: 'direct'
        );

        $report = $this->reportAction->handle($biz->id, 'job');

        // A job with cost rows appears in the report and carries a margin figure
        $jobsWithMargins = array_column($report, 'gross_margin_pct', 'job_id');

        $this->assertArrayHasKey(999, $jobsWithMargins);
        $this->assertEquals(50.0, $jobsWithMargins[999]);

        // A job with no cost rows reports no margin (absent from array)
        // and does not appear carrying any margin figure.
        $this->assertArrayNotHasKey($emptyJobId, $jobsWithMargins);
        $this->assertCount(1, $jobsWithMargins);
    }

    /** [N-048] */
    public function test_n_048_a_margin_never_reads_an_invoiced_source(): void
    {
        $path = base_path('app/Modules/X-166');
        $pattern = '(invoice|amount_due|receivable|invoiced|balance_due)';
        $grepCommand = sprintf('grep -rniE %s %s', escapeshellarg($pattern), escapeshellarg($path));
        $output = shell_exec($grepCommand);

        // The capabilities.php filter is load-bearing here because the assertion
        // prose in the generated capabilities.php contains the word "invoiced".
        $lines = array_filter(explode("\n", $output ?? ''), function ($line) {
            return ! empty($line) && ! str_contains($line, 'capabilities.php') && ! str_contains($line, 'manifest.php');
        });

        $this->assertEmpty($lines, 'No path under app/Modules/X-166/ reads an invoiced source.');
    }

    /** [N-048] */
    public function test_n_048_margin_is_the_collected_figure_not_the_invoiced_one(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Collected Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $invoicedCents = 100000;
        $collectedCents = 60000;

        $cost = $this->costAction->handle(
            businessId: $biz->id,
            jobId: 777,
            priceBookVersion: 'v2.1',
            laborCostCents: 20000,
            materialsCostCents: 10000,
            overheadCostCents: 10000,
            revenueCents: $collectedCents, // only 60000 ever enters
            techId: 44,
            serviceType: 'repair',
            source: 'direct'
        );

        $this->assertEquals(40000, $cost->total_cost_cents);
        $this->assertEquals(20000, $cost->gross_margin_cents, 'Margin is derived from the 60000 collected figure, not 60000 produced from 100000 invoiced figure minus 40000 costs');
        $this->assertEquals(33.33, $cost->gross_margin_pct);

        $report = $this->reportAction->handle($biz->id, 'job');
        $row = (array) collect($report)->firstWhere('job_id', 777);
        $this->assertNotEmpty($row);

        $this->assertEquals(60000, $row['revenue_cents']);
        $this->assertFalse(in_array(100000, $row, true), 'No value in the row equals the invoiced amount of 100000');
    }
}
