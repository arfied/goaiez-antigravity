<?php

declare(strict_types=1);

namespace Tests\Modules\X166;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X166\Actions\JobCostAction;
use App\Modules\X166\Events\JobCosted;
use App\Modules\X166\Events\MarginBelowThreshold;
use App\Modules\X166\Ui\BySource;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class BySourceTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(BySource::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(BySource::class)
            ->assertSee('No margin by source yet. Costed jobs roll up here.');
    }

    public function test_seeded_sources_show_rollups(): void
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
            jobId: 201,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            source: 'inbound_call'
        );
        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 202,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            source: 'inbound_call'
        );

        app(JobCostAction::class)->handle(
            businessId: $biz->id,
            jobId: 203,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 30000,
            source: 'web_form'
        );

        Livewire::actingAs($user)->test(BySource::class)
            ->assertSee('inbound_call')
            ->assertSee('web_form')
            ->assertSee('400.00')->assertSee('220.00')->assertSee('55.00 %');
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
            jobId: 201,
            priceBookVersion: 'v2.1',
            laborCostCents: 5000,
            materialsCostCents: 3000,
            overheadCostCents: 1000,
            revenueCents: 20000,
            source: 'inbound_call'
        );

        Livewire::actingAs($user)->test(BySource::class)
            ->assertDontSee('Job #201')
            ->call('toggle', 'inbound_call')
            ->assertSee('Job #201');
    }
}
