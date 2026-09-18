<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Ui\AgencyConsole;
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

        \App\Support\Tenancy::setUser($owner->id);
        $agency = \App\Modules\X112\Models\Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        \App\Modules\X112\Models\AgencyClient::create([
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'client_business_id' => $biz->id,
            'client_name' => 'Client Alpha',
            'status' => 'active',
        ]);
        \App\Support\Tenancy::forget();

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
}
