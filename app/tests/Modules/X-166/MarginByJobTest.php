<?php

declare(strict_types=1);

namespace Tests\Modules\X166;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X166\Actions\JobCostAction;
use App\Modules\X166\Events\JobCosted;
use App\Modules\X166\Events\MarginBelowThreshold;
use App\Modules\X166\Models\JobCost;
use App\Modules\X166\Ui\MarginByJob;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class MarginByJobTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(MarginByJob::class)->assertForbidden();
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(MarginByJob::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(MarginByJob::class)
            ->assertSee('No costed jobs yet. A job is costed when it completes.');
    }

    public function test_seeded_row_shows_values_and_no_sample_pill(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 101,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000
        );

        Livewire::actingAs($user)->test(MarginByJob::class)
            ->assertSee('v2.1')
            ->assertSee('200.00')
            ->assertSee('90.00')
            ->assertSee('110.00')
            ->assertSee('55.00 %')
            ->assertDontSee('<span>Sample</span>', false);
    }

    public function test_sample_row_shows_sample_pill(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 102,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000
        );
        JobCost::where('job_id', 102)->update(['is_sample' => true]);

        Livewire::actingAs($user)->test(MarginByJob::class)
            ->assertSee('<span>Sample</span>', false);
    }

    public function test_toggle_shows_breakdown(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        $cost = app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 103,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000
        );

        Livewire::actingAs($user)->test(MarginByJob::class)
            ->assertDontSee('50.00')
            ->call('toggle', $cost->id)
            ->assertSee('50.00');
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 999,
            priceBookVersion: 'v2.1',
            laborCostCents: 10000,
            materialsCostCents: 10000,
            overheadCostCents: 10000,
            revenueCents: 81123
        );

        $this->actingAs($user);
        $this->get(route('x-166.margin-by-job'))->assertOk()->assertSee('811.23');
    }

    public function test_3c_sample_job_is_visible_on_per_job_screen(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        JobCost::create([
            'business_id' => $biz->id,
            'job_id' => 105,
            'price_book_version' => 'v1',
            'tech_id' => 70,
            'service_type' => 'diagnostic',
            'source' => 'direct',
            'revenue_cents' => 99999,
            'total_cost_cents' => 1000,
            'labor_cost_cents' => 500,
            'materials_cost_cents' => 500,
            'overhead_cost_cents' => 0,
            'gross_margin_cents' => 98999,
            'gross_margin_pct' => 99.0,
            'is_sample' => true,
        ]);

        $component = Livewire::actingAs($user)->test(MarginByJob::class);
        $html = $component->html();

        $this->assertStringContainsString('999.99', $html, 'The per-job screen must still list a sample job');
        $this->assertStringContainsString('<span>Sample</span>', $html, 'The per-job screen must show the sample pill');
    }

    public function test_record_job_cost_from_screen_and_feeds_by_service(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        $this->actingAs($user);

        $this->get(route('x-166.margin-by-job'))->assertOk()->assertSee('No costed jobs yet.');

        Livewire::actingAs($user)->test(MarginByJob::class)
            ->set('jobId', '500')
            ->set('priceBookVersion', 'v1.5')
            ->set('revenueCents', '10000')
            ->set('laborCostCents', '1000')
            ->set('materialsCostCents', '1000')
            ->set('overheadCostCents', '1000')
            ->call('recordJobCost')
            ->assertSee('Recorded job cost for job 500.')
            ->assertSet('jobId', '');

        $this->assertDatabaseHas('job_costs', [
            'business_id' => $biz->id,
            'job_id' => 500,
            'revenue_cents' => 10000,
        ]);

        $this->get(route('x-166.margin-by-job'))->assertOk()->assertSee('500')->assertSee('100.00');

        $this->get(route('x-166.by-service'))->assertOk()->assertDontSee('No costed jobs yet.');
    }

    public function test_missing_job_id_writes_no_row_and_shows_reason(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        Livewire::actingAs($user)->test(MarginByJob::class)
            ->set('jobId', '')
            ->set('revenueCents', '10000')
            ->call('recordJobCost')
            ->assertSee('Job ID is required.');

        $this->assertDatabaseMissing('job_costs', [
            'business_id' => $biz->id,
        ]);
    }
}
