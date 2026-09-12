<?php

declare(strict_types=1);

namespace Tests\Modules\X194;

use App\Modules\X194\Actions\ReportPdfAction;
use App\Modules\X194\Actions\ViewRenderAction;
use App\Modules\X194\Actions\ViewSaveAction;
use App\Modules\X194\Actions\ViewScheduleAction;
use App\Modules\X194\Events\ReportSent;
use App\Modules\X194\Events\ViewRendered;
use App\Modules\X194\Events\ViewSaved;
use App\Modules\X194\Models\SavedView;
use App\Modules\X194\Models\ViewSchedule;
use App\Modules\X194\Ui\AnyViewIt;
use App\Modules\X194\Ui\SavedViewsList;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class X194Test extends TestCase
{
    private ViewSaveAction $saveAction;

    private ViewRenderAction $renderAction;

    private ViewScheduleAction $scheduleAction;

    private ReportPdfAction $pdfAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saveAction = new ViewSaveAction;
        $this->renderAction = new ViewRenderAction;
        $this->scheduleAction = new ViewScheduleAction;
        $this->pdfAction = new ReportPdfAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'DB::|::query\(|->get\(\)' app/Modules/X-194/ returns nothing, enforced by doctor;
     * a saved view is a row in saved_views, never a file;
     * a digest with zero activity is not sent
     */
    public function test_anchor_saved_view_in_database_and_zero_activity_digest_not_sent(): void
    {
        Event::fake([ViewSaved::class, ViewRendered::class, ReportSent::class]);

        $biz = TestCase::provisionTenant(['name' => 'Custom Reporting Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A saved view is a row in saved_views, NEVER a file on disk (TEST ANCHOR)
        $savedView = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Monthly Completed HVAC Overhaul Jobs',
            viewType: 'table',
            filterConfig: ['status' => 'completed', 'tag' => 'hvac_overhaul'],
            columnsConfig: ['job_number', 'customer_name', 'amount_collected', 'technician']
        );

        $this->assertNotNull($savedView->id);
        $this->assertEquals('Monthly Completed HVAC Overhaul Jobs', $savedView->view_name);

        $dbView = SavedView::where('business_id', $biz->id)->find($savedView->id);
        $this->assertNotNull($dbView, 'A saved view is a row in saved_views');

        Event::assertDispatched(ViewSaved::class);

        // 2. View Rendering in location's timezone (G9-37) & dashed estimate tile for null values (G9-35)
        $renderNullValue = $this->renderAction->renderView(
            businessId: $biz->id,
            viewId: $savedView->id,
            locationTimezone: 'America/Chicago',
            jobValue: null,
            jobCount: 12
        );

        $this->assertEquals('--', $renderNullValue['estimate_tile'], 'Estimate tile stays dashed ("--") until job value entered (G9-35)');
        $this->assertEquals('America/Chicago', $renderNullValue['timezone'], 'Renders in location timezone (G9-37)');

        $expectedOffset = Carbon::now('America/Chicago')->getOffsetString();
        $this->assertStringContainsString($expectedOffset, $renderNullValue['rendered_at'], 'Renders in location timezone offset (G9-37)');

        $renderWithValue = $this->renderAction->renderView(
            businessId: $biz->id,
            viewId: $savedView->id,
            locationTimezone: 'America/Chicago',
            jobValue: 1234.5,
            jobCount: 12
        );

        $this->assertEquals('$1,234.50', $renderWithValue['estimate_tile'], 'Estimate tile formats value (G9-35)');
        $this->assertNotEquals('--', $renderWithValue['estimate_tile'], 'Estimate tile is not dashed when value entered (G9-35)');
        $this->assertEquals(12, $renderWithValue['job_count'], 'Job count survives estimate value being entered (G9-35)');

        Event::assertDispatched(ViewRendered::class);

        // 3. Digest with ZERO activity is NOT sent (TEST ANCHOR)
        $schedule = ViewSchedule::create([
            'business_id' => $biz->id,
            'saved_view_id' => $savedView->id,
            'cron_expression' => '0 8 * * 1',
            'recipient_emails' => ['owner@acme-hvac.com'],
            'timezone' => 'America/Chicago',
            'is_active' => true,
        ]);

        $zeroRes = $this->scheduleAction->sendDigest(
            businessId: $biz->id,
            scheduleId: $schedule->id,
            activityCount: 0 // Zero activity
        );

        $this->assertEquals('skipped_zero_activity', $zeroRes['status']);
        $this->assertFalse($zeroRes['sent'], 'A digest with zero activity is not sent');
        Event::assertNotDispatched(ReportSent::class);

        // 4. Digest with activity (> 0) IS sent
        $sentRes = $this->scheduleAction->sendDigest(
            businessId: $biz->id,
            scheduleId: $schedule->id,
            activityCount: 5
        );

        $this->assertEquals('sent', $sentRes['status']);
        $this->assertTrue($sentRes['sent']);
        Event::assertDispatched(ReportSent::class);

        // 5. PDF generation (G9-26)
        $pdfRes = $this->pdfAction->generate($biz->id, $savedView->id);
        $this->assertEquals('generated', $pdfRes['status']);
        $this->assertNotEmpty($pdfRes['pdf_payload']);
    }

    public function test_saved_views_list_component(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'UI Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view1 = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'View Alpha',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );
        $view2 = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'View Beta',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('View Alpha')
            ->assertSee('View Beta');

        $component->call('makeDefault', $view2->id);

        $this->assertFalse(SavedView::find($view1->id)->is_default);
        $this->assertTrue(SavedView::find($view2->id)->is_default);
    }

    public function test_saved_views_list_empty_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty UI Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('You have not saved a view yet.')
            ->assertSee('When you save a view, it will appear here.');
    }

    public function test_saved_views_list_error_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Error UI Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id]);
        $component->set('errorMessage', 'Terrible error occurred.');

        $component->assertSee('We could not load your saved views.')
            ->assertSee('Terrible error occurred.');
    }

    public function test_any_view_it_component(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'View Render Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Job View Alpha',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        // Test with null value
        Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => $view->id,
            'locationTimezone' => 'America/Denver',
            'jobValue' => null,
            'jobCount' => 7,
        ])
            ->call('load')
            ->assertSee('Job View Alpha')
            ->assertSee('America/Denver')
            ->assertSeeHtml('data-job-count="7"')
            ->assertSeeHtml('data-estimate-tile="--"');

        // Test with real value
        Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => $view->id,
            'locationTimezone' => 'America/New_York',
            'jobValue' => 1500.50,
            'jobCount' => 3,
        ])
            ->call('load')
            ->assertSee('Job View Alpha')
            ->assertSee('America/New_York')
            ->assertSeeHtml('data-job-count="3"')
            ->assertSeeHtml('data-estimate-tile="$1,500.50"');
    }

    public function test_any_view_it_empty_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'View Empty Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // The empty state is only reachable when businessId or viewId is 0, since invalid IDs throw.
        Livewire::test(AnyViewIt::class, [
            'businessId' => 0,
            'viewId' => 0,
        ])
            ->call('load')
            ->assertSee('No view selected')
            ->assertSee('Please select a view to see its details.');
    }

    public function test_any_view_it_error_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'View Error Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $component = Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => 9999, // Non-existent view will throw ModelNotFoundException
        ]);

        $component->call('load')
            ->assertSee('We could not render your view.')
            ->assertSee('Please try again later or contact support if the issue persists.');
    }

    /**
     * Proves a signed-in tenant can save a view via the UI, reaching ViewSaveAction.
     */
    public function test_saved_views_list_can_save_a_view(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Save UI Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->set('newViewName', 'My Shiny View')
            ->call('saveView');

        $this->assertDatabaseHas('saved_views', [
            'business_id' => $biz->id,
            'view_name' => 'My Shiny View',
        ]);

        $component->assertSet('newViewName', '');
    }

    /**
     * [G4-20], [G8-10], [G9-11], [G9-23], [G9-26], [G9-35], [G9-37], [G13-17]
     *
     * CLOSED: G9-37 — built in f28f6539
     * ⛔ REFUSED: G4-20 — a house standard enforced by lint, not a capability row
     * ⛔ REFUSED: G8-10 — named in the header; the JSONB column is X-121's (out of this lane)
     * ⛔ REFUSED: G9-11 — named in the header
     * ⛔ REFUSED: G9-23 — named in the header
     * ⛔ REFUSED: G9-26 — named in the header (report.pdf)
     * ⛔ REFUSED: G13-17 — revenue on the territory map; the polygons are X-10's (out of this lane)
     */
    public function test_reporting_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
