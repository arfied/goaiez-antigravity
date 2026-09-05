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
}
