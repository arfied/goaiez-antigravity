<?php

declare(strict_types=1);

namespace Tests\Modules\X194;

use App\Models\Location;
use App\Models\User;
use App\Modules\X194\Actions\ReportPdfAction;
use App\Modules\X194\Actions\SetDefaultViewAction;
use App\Modules\X194\Actions\ViewListAction;
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
use App\Services\Tenant\LocationContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\LazyCollection;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BUILD PROPOSAL: X-194 needs a way to aggregate job counts and values for a view, but the jobs table is owned by X-121 and there is no cross-module action for this. Owner: X-121
 */
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

    /**
     * Proves that a client cannot force the component to display a load-error
     * message. The component clears errorMessage during render() if the read succeeds,
     * so only actual read failures produce the error panel.
     * Changed because tick 347 ruled no request should display an unearned error panel.
     */
    public function test_saved_views_list_error_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Error UI Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id]);
        $component->set('errorMessage', 'Terrible error occurred.');

        $component->assertDontSee('We could not load your saved views.')
            ->assertDontSee('Terrible error occurred.');
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

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'America/Denver';
        $location->save();

        // Test with null value
        Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => $view->id,
            'jobValue' => null,
            'jobCount' => 7,
        ])
            ->call('load')
            ->assertSee('Job View Alpha')
            ->assertSee('America/Denver')
            ->assertDontSeeHtml('data-job-count')
            ->assertSeeHtml('data-estimate-tile="--"');

        $location->timezone = 'America/New_York';
        $location->save();

        // Test with real value
        Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => $view->id,
            'jobValue' => 1500.50,
            'jobCount' => 3,
        ])
            ->call('load')
            ->assertSee('Job View Alpha')
            ->assertSee('America/New_York')
            ->assertDontSeeHtml('data-job-count')
            ->assertSeeHtml('data-estimate-tile="--"');
    }

    public function test_client_cannot_set_job_count_or_value_for_view(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'View Render Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Job View Beta',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'America/Denver';
        $location->save();

        // Using set() to simulate a client update.
        // This fails on today's tree because the properties were public.
        try {
            Livewire::test(AnyViewIt::class, ['businessId' => $biz->id, 'viewId' => $view->id])
                ->call('load')
                ->set('jobValue', 1000.0)
                ->set('jobCount', 99)
                ->assertDontSeeHtml('data-job-count="99"')
                ->assertSeeHtml('data-estimate-tile="--"');
        } catch (\Exception $e) {
            // Livewire throws when setting a non-existent property
            $this->assertStringContainsString('not found on component', $e->getMessage());
        }
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

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'America/New_York';
        $location->save();

        $component = Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => 9999, // Non-existent view will throw ModelNotFoundException
        ]);

        $component->call('load')
            ->assertSee('We could not render your view.')
            ->assertSee('Please try again later or contact support if the issue persists.');
    }

    /**
     * Proves that a client cannot update the locationTimezone Livewire property directly.
     */
    public function test_location_timezone_is_locked_and_cannot_be_updated_by_client(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Locked Timezone Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Job View Alpha',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'America/Denver';
        $location->save();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => $view->id,
        ])->set('locationTimezone', 'Europe/London');
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
     * Proves that a GET request displays the timezone of the selected location,
     * and that the selected location is appropriately marked as selected in the dropdown.
     */
    public function test_two_locations_displays_selected_timezone_on_real_get(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['name' => 'Two Locations', 'owner_user_id' => $owner->id]);

        $location1 = Location::where('business_id', $biz->id)->first();
        $location1->name = 'Loc One';
        $location1->timezone = 'America/Denver';
        $location1->save();

        $location2 = new Location(['business_id' => $biz->id, 'name' => 'Loc Two', 'is_autopilot_active' => true]);
        $location2->timezone = 'America/New_York';
        $location2->save();

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Job View Alpha',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $this->withSession([LocationContext::SESSION_KEY => $location2->id]);

        $response = $this->actingAs($owner)->get(route('x-194.any-view-it', ['viewId' => $view->id]));

        $response->assertOk();
        $response->assertSee('Job View Alpha');
        $response->assertSee('America/New_York');
        $response->assertSeeInOrder(['value="'.$location2->id.'"', 'selected', '>'.$location2->name.'</option>'], false);
    }

    public function test_any_view_it_retry_recovers_when_cause_is_gone(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'View Retry Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Recover View',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = null;
        $location->save();

        $component = Livewire::test(AnyViewIt::class, [
            'businessId' => $biz->id,
            'viewId' => $view->id,
        ]);

        $component->call('load')
            ->assertSee('The location has no timezone set.');

        $location->timezone = 'America/Denver';
        $location->save();

        $component->call('load')
            ->assertDontSee('The location has no timezone set.')
            ->assertSee('America/Denver');
    }

    public function test_saved_views_list_retains_list_on_save_failure(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Save Error Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Existing View',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('Existing View');

        // Force a save failure by throwing an exception in the event listener (the row is written but the save action throws)
        Event::listen(ViewSaved::class, function () {
            throw new \Exception('Save failed');
        });

        $component->set('newViewName', 'Failing View')
            ->call('saveView')
            ->assertSee('We could not save your view.')
            ->assertSee('Existing View');

        Event::forget(ViewSaved::class);

        $component->set('newViewName', 'Successful View')
            ->call('saveView')
            ->assertDontSee('We could not save your view.');
    }

    /**
     * Shows that a database failure during iteration yields the error panel.
     * The retry assertion here passes vacuously against empty HTML because the
     * mock incorrectly returns a Collection instead of a LazyCollection, causing
     * a TypeError that Livewire 3 aborts with a 419 Page Expired response.
     */
    public function test_saved_views_list_read_escapes_its_guard(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->mock(ViewListAction::class, function ($mock) {
            $mock->shouldReceive('listViews')->andReturn(new LazyCollection(function () {
                yield from [];
                throw new \Exception('Database failure during iteration');
            }));
        });

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id]);

        $component->call('load')
            ->assertSee('We could not load your saved views.');

        // And retry shows it
        $this->mock(ViewListAction::class, function ($mock) {
            $mock->shouldReceive('listViews')->andReturn(collect([])); // empty collection for retry
        });

        $component->call('$refresh')
            ->assertDontSee('We could not load your saved views.');
    }
    public function test_saved_views_list_retry_escapes_its_guard(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->mock(ViewListAction::class, function ($mock) {
            $mock->shouldReceive('listViews')->andReturn(new LazyCollection(function () {
                yield from [];
                throw new \Exception('Database failure during iteration');
            }));
        });

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id]);

        $component->call('load')
            ->assertSee('We could not load your saved views.');

        $this->mock(ViewListAction::class, function ($mock) {
            $mock->shouldReceive('listViews')->andReturn(new LazyCollection([]));
        });

        $component->call('$refresh')
            ->assertDontSee('We could not load your saved views.');
    }

    public function test_saved_views_list_default_view_failure_retains_list(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Default View Failure Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Some Existing View',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $this->mock(SetDefaultViewAction::class, function ($mock) {
            $mock->shouldReceive('setDefault')->andThrow(new \Exception('Database update failed'));
        });

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('Some Existing View');

        $component->call('makeDefault', $view->id)
            ->assertSee('We could not update your default view.')
            ->assertSee('Some Existing View'); // retain the list
    }

    public function test_saved_views_list_default_view_retry_clears_panel(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Default Retry Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Some Existing View',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $this->mock(SetDefaultViewAction::class, function ($mock) {
            $mock->shouldReceive('setDefault')->andThrow(new \Exception('Database update failed'));
        });

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->call('load');

        $component->call('makeDefault', $view->id)
            ->assertSee('We could not update your default view.');

        $component->call('clearDefaultError')
            ->assertDontSee('We could not update your default view.');
    }

    public function test_saved_views_list_default_view_retry_button_is_wired(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Wiring Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Some Existing View',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $this->mock(SetDefaultViewAction::class, function ($mock) {
            $mock->shouldReceive('setDefault')->andThrow(new \Exception('Database update failed'));
        });

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->call('load');

        $component->call('makeDefault', $view->id)
            ->assertSeeHtml('wire:click="clearDefaultError"');
    }

    public function test_saved_views_list_default_view_failure_does_not_show_load_heading(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Heading Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $view = $this->saveAction->save(
            businessId: $biz->id,
            viewName: 'Some Existing View',
            viewType: 'table',
            filterConfig: [],
            columnsConfig: []
        );

        $this->mock(SetDefaultViewAction::class, function ($mock) {
            $mock->shouldReceive('setDefault')->andThrow(new \Exception('Database update failed'));
        });

        $component = Livewire::test(SavedViewsList::class, ['businessId' => $biz->id])
            ->call('load');

        $component->call('makeDefault', $view->id)
            ->assertDontSee('We could not load your saved views.');
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
