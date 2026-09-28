<?php

declare(strict_types=1);

namespace Tests\Modules\X177\Screens;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Ui\GbpCard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class GbpCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-177.gbp-card'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Connect Google');

        Tenancy::setUser($owner->id);
        $loc = Location::factory()->create(['business_id' => $biz->id]);
        $conn = GbpConnection::create([
            'business_id' => $biz->id,
            'location_id' => $loc->id,
            'account_ref' => 'acct_distinctive_4502',
            'external_label' => 'Distinctive Store 4502',
            'profile_status' => 'active',
        ]);
        GbpPost::create([
            'business_id' => $biz->id,
            'connection_id' => $conn->id,
            'content' => 'Distinctive post 4502',
        ]);
        Tenancy::forget();

        $this->get(route('x-177.gbp-card'))
            ->assertOk()
            ->assertSee('Distinctive Store 4502')
            ->assertSee('not reading your profile')
            ->assertSee('Distinctive post 4502')
            ->assertDontSee('Connect Google');

        Livewire::test(GbpCard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-177.gbp-card.admin'))->assertOk();

        Livewire::test(GbpCard::class)->assertOk();
    }
}
