<?php

declare(strict_types=1);

namespace Tests\Modules\X166;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X166\Actions\JobCostAction;
use App\Modules\X166\Events\JobCosted;
use App\Modules\X166\Events\MarginBelowThreshold;
use App\Modules\X166\Ui\ByTech;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class ByTechTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(ByTech::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(ByTech::class)
            ->assertSee('No margin by technician yet. Costed jobs roll up here.');
    }

    public function test_seeded_techs_show_rollups(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([JobCosted::class, MarginBelowThreshold::class]);

        $tech1 = User::factory()->create(['name' => 'Alice']);
        $tech2 = User::factory()->create(['name' => 'Bob']);

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 201,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            techId: $tech1->id
        );
        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 202,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            techId: $tech1->id
        );

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 203,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 30000,
            techId: $tech2->id
        );

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 204,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 30000,
            techId: null
        );

        Livewire::actingAs($user)->test(ByTech::class)
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee('400.00')->assertSee('220.00')->assertSee('55.00 %') // Alice's total revenue
            ->assertSee('Unassigned');
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

        $tech1 = User::factory()->create(['name' => 'Alice']);
        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 201,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            techId: $tech1->id
        );

        Livewire::actingAs($user)->test(ByTech::class)
            ->assertDontSee('Job #201')
            ->call('toggle', (string) $tech1->id)
            ->assertSee('Job #201');
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
            revenueCents: 81123,
            techId: null
        );

        $this->actingAs($user);
        $this->get(route('x-166.by-tech'))->assertOk()->assertSee('811.23');
    }
}
