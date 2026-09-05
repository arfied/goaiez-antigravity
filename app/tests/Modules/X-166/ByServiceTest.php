<?php

declare(strict_types=1);

namespace Tests\Modules\X166;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X166\Actions\JobCostAction;
use App\Modules\X166\Events\JobCosted;
use App\Modules\X166\Events\MarginBelowThreshold;
use App\Modules\X166\Ui\ByService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class ByServiceTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(ByService::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(ByService::class)
            ->assertSee('No margin by service yet. Costed jobs roll up here.');
    }

    public function test_seeded_services_show_rollups(): void
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
            jobId: 301,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            techId: null,
            serviceType: 'plumbing'
        );
        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 302,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            techId: null,
            serviceType: 'plumbing'
        );

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 303,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 30000,
            techId: null,
            serviceType: 'electrical'
        );

        Livewire::actingAs($user)->test(ByService::class)
            ->assertSee('plumbing')
            ->assertSee('electrical')
            ->assertSee('400.00') // plumbing total revenue
            ->assertSee('220.00') // plumbing total margin
            ->assertSee('55.00 %'); // plumbing margin %
    }

    public function test_toggle_shows_jobs(): void
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
            jobId: 301,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            techId: null,
            serviceType: 'plumbing'
        );

        Livewire::actingAs($user)->test(ByService::class)
            ->assertDontSee('Job #301')
            ->call('toggle', 'plumbing')
            ->assertSee('Job #301');
    }
}
