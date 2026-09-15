<?php

declare(strict_types=1);

namespace Tests\Modules\X206\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X206\Models\Credential;
use App\Modules\X206\Ui\Connections;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectionsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-206.connections'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No credential connections configured.');

        Tenancy::set((int) $biz->id);
        Credential::create([
            'business_id' => $biz->id,
            'service_name' => 'distinctive_service_4491',
            'encrypted_secret' => 'enc_distinctive_4491',
            'key_hint' => 'hint4491',
        ]);
        Tenancy::forget();

        $this->get(route('x-206.connections'))
            ->assertOk()
            ->assertSee('distinctive_service_4491')
            ->assertSee('(hint4491)')
            ->assertDontSee('No credential connections configured.');

        Livewire::actingAs($owner)->test(Connections::class, ['businessId' => $biz->id])->assertOk();
    }
}
