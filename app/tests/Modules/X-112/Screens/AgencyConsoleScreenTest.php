<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\AgencyClient;
use App\Modules\X112\Ui\AgencyConsole;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyConsoleScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-112.agency-console'))
            ->assertOk()
            ->assertSee('Agency Console')
            ->assertSee('No managed clients provisioned.');

        Tenancy::setUser($owner->id);
        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        AgencyClient::create([
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'client_business_id' => $biz->id,
            'client_name' => 'Client Alpha',
            'status' => 'active',
        ]);
        Tenancy::forget();

        $this->get(route('x-112.agency-console'))
            ->assertOk()
            ->assertSee('Client Alpha')
            ->assertSee('active')
            ->assertDontSee('No managed clients provisioned.');

        Livewire::test(AgencyConsole::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-112.agency-console.admin'))->assertOk();

        Livewire::test(AgencyConsole::class)->assertOk();
    }

    public function test_agency_creation(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. agencies empty state visible on a GET before anything is created
        $this->get(route('x-112.agency-console'))
            ->assertOk()
            ->assertSee('No agencies provisioned.');

        Tenancy::setUser($owner->id);

        // 2. control creating one
        Livewire::test(AgencyConsole::class)
            ->set('agencyName', 'Test Agency')
            ->set('whitelabelDomain', 'test.com')
            ->set('agencyMode', 'full_service')
            ->call('createAgency')
            ->assertSet('success', function ($value) {
                return str_contains($value, 'Created agency Test Agency with ID ') && str_contains($value, 'Nothing else is wired to it yet.');
            })
            ->assertSet('error', null);

        $agency = Agency::where('business_id', $biz->id)->where('agency_name', 'Test Agency')->firstOrFail();

        $this->assertDatabaseHas((new Agency)->getTable(), [
            'id' => $agency->id,
            'business_id' => $biz->id,
            'agency_name' => 'Test Agency',
            'whitelabel_domain' => 'test.com',
            'agency_mode' => 'full_service',
        ]);

        Tenancy::forget();

        // 3. GET afterwards showing agency and losing empty state, clients list empty state still present
        $this->get(route('x-112.agency-console'))
            ->assertOk()
            ->assertSee('Test Agency')
            ->assertSee('test.com')
            ->assertDontSee('No agencies provisioned.')
            ->assertSee('No managed clients provisioned.');

        Tenancy::setUser($owner->id);

        // 4. duplicate refusal
        Livewire::test(AgencyConsole::class)
            ->set('agencyName', 'Test Agency')
            ->set('whitelabelDomain', 'test2.com')
            ->set('agencyMode', 'full_service')
            ->call('createAgency')
            ->assertSet('error', "Agency 'Test Agency' already exists.");

        $this->assertEquals(1, Agency::where('business_id', $biz->id)->where('agency_name', 'Test Agency')->count());

        // 5. invalid agencyMode refused
        Livewire::test(AgencyConsole::class)
            ->set('agencyName', 'Another Agency')
            ->set('agencyMode', 'invalid_mode')
            ->call('createAgency')
            ->assertSet('error', 'Invalid agency mode');

        $this->assertDatabaseMissing((new Agency)->getTable(), [
            'agency_name' => 'Another Agency',
        ]);

        // 6. empty agencyName refused
        Livewire::test(AgencyConsole::class)
            ->set('agencyName', '  ')
            ->call('createAgency')
            ->assertSet('error', 'Agency name is required.');

        $this->assertDatabaseMissing((new Agency)->getTable(), [
            'agency_name' => '',
        ]);
        $this->assertDatabaseMissing((new Agency)->getTable(), [
            'agency_name' => '  ',
        ]);

        Tenancy::forget();
    }

    public function test_client_onboarding(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. clients empty state visible on a GET before anything is created
        $this->get(route('x-112.agency-console'))
            ->assertOk()
            ->assertSee('No managed clients provisioned.');

        Tenancy::setUser($owner->id);

        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);

        // 2. control creating one
        Livewire::test(AgencyConsole::class)
            ->set('agencyId', $agency->id)
            ->set('clientName', 'New Client')
            ->call('onboardClient')
            ->assertSet('success', function ($value) {
                return str_contains((string) $value, 'Onboarded client New Client') && str_contains((string) $value, 'This provisions a whole new tenant');
            })
            ->assertSet('error', null);

        $client = AgencyClient::where('agency_id', $agency->id)->where('client_name', 'New Client')->firstOrFail();

        $this->assertDatabaseHas((new AgencyClient)->getTable(), [
            'id' => $client->id,
            'agency_id' => $agency->id,
            'client_name' => 'New Client',
        ]);

        Tenancy::forget();

        // 3. GET afterwards showing client and losing empty state
        $this->get(route('x-112.agency-console'))
            ->assertOk()
            ->assertSee('New Client')
            ->assertDontSee('No managed clients provisioned.');

        Tenancy::setUser($owner->id);

        // 4. empty agencyId refused
        Livewire::test(AgencyConsole::class)
            ->set('agencyId', 0)
            ->set('clientName', 'Refused Client')
            ->call('onboardClient')
            ->assertSet('error', 'Agency ID is required.');

        $this->assertDatabaseMissing((new AgencyClient)->getTable(), [
            'client_name' => 'Refused Client',
        ]);

        // 5. empty clientName refused
        Livewire::test(AgencyConsole::class)
            ->set('agencyId', $agency->id)
            ->set('clientName', '  ')
            ->call('onboardClient')
            ->assertSet('error', 'Client name is required.');

        $this->assertDatabaseMissing((new AgencyClient)->getTable(), [
            'client_name' => '  ',
        ]);

        Tenancy::forget();
    }
}
