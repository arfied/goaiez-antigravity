<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\ImpersonationLog;
use App\Modules\X112\Ui\ImpersonationLogView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ImpersonationLogViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        ImpersonationLog::create([
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'target_client_business_id' => $biz->id,
            'reason' => 'Debugging connection',
        ]);
        Tenancy::forget();

        $this->get(route('x-112.impersonation-log'))
            ->assertOk()
            ->assertSee('Debugging connection');

        Livewire::test(ImpersonationLogView::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('Debugging connection');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-112.impersonation-log.admin'))->assertOk();

        Livewire::test(ImpersonationLogView::class)->assertOk();
    }

    public function test_impersonation_chain(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $this->get(route('x-112.impersonation-log'))
            ->assertOk()
            ->assertSee('No impersonation records found.');

        // 1. Create agency
        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Chain Agency',
            'whitelabel_domain' => 'chain.example',
            'agency_mode' => 'full_service',
        ]);
        
        // 2. Onboard client
        $clientAction = app(\App\Modules\X112\Actions\AgencyOnboardClientAction::class);
        $client = $clientAction->handle($biz->id, $agency->id, 'Target Client');

        // 3. Impersonate
        Livewire::test(ImpersonationLogView::class, ['businessId' => $biz->id])
            ->set('agencyId', $agency->id)
            ->set('userId', $owner->id)
            ->set('targetClientBusinessId', $client->client_business_id)
            ->set('reason', 'Investigating chain')
            ->call('startImpersonation')
            ->assertSet('success', function ($value) use ($client, $owner, $agency) {
                return str_contains((string) $value, "Started impersonation of client {$client->client_business_id} by user {$owner->id} under agency {$agency->id}");
            })
            ->assertSet('error', null);

        $this->assertDatabaseHas((new ImpersonationLog)->getTable(), [
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'target_client_business_id' => $client->client_business_id,
            'reason' => 'Investigating chain',
        ]);

        Tenancy::forget();

        $this->get(route('x-112.impersonation-log'))
            ->assertOk()
            ->assertSee('Investigating chain')
            ->assertDontSee('No impersonation records found.');
            
        Tenancy::setUser($owner->id);

        // Refusals
        Livewire::test(ImpersonationLogView::class, ['businessId' => $biz->id])
            ->set('agencyId', 0)
            ->set('userId', $owner->id)
            ->set('targetClientBusinessId', $client->client_business_id)
            ->set('reason', 'Missing agency')
            ->call('startImpersonation')
            ->assertSet('error', 'Agency ID is required.');

        $this->assertDatabaseMissing((new ImpersonationLog)->getTable(), [
            'reason' => 'Missing agency',
        ]);

        Livewire::test(ImpersonationLogView::class, ['businessId' => $biz->id])
            ->set('agencyId', $agency->id)
            ->set('userId', 0)
            ->set('targetClientBusinessId', $client->client_business_id)
            ->set('reason', 'Missing user')
            ->call('startImpersonation')
            ->assertSet('error', 'User ID is required.');
            
        Livewire::test(ImpersonationLogView::class, ['businessId' => $biz->id])
            ->set('agencyId', $agency->id)
            ->set('userId', $owner->id)
            ->set('targetClientBusinessId', 0)
            ->set('reason', 'Missing target')
            ->call('startImpersonation')
            ->assertSet('error', 'Target Client Business ID is required.');

        Livewire::test(ImpersonationLogView::class, ['businessId' => $biz->id])
            ->set('agencyId', $agency->id)
            ->set('userId', $owner->id)
            ->set('targetClientBusinessId', $client->client_business_id)
            ->set('reason', '  ')
            ->call('startImpersonation')
            ->assertSet('error', 'Reason is required.');
            
        Tenancy::forget();
    }
}
