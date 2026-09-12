<?php

declare(strict_types=1);

namespace Tests\Modules\X108\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Modules\X108\Ui\Waitlist;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class WaitlistScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-108.waitlist'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('Nobody is waiting')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(WaitlistJoinAction::class)->handle((int) $biz->id, 'Dana Whitfield', '+15125550199', 'Haircut', now()->addDay()->toDateString());
        Tenancy::forget();

        $this->get(route('x-108.waitlist'))
            ->assertOk()
            ->assertSee('Dana Whitfield')
            ->assertSee('Haircut')
            ->assertDontSee('Nobody is waiting');

        Livewire::test(Waitlist::class)->assertOk();
    }
}
