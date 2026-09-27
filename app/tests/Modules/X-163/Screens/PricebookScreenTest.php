<?php

declare(strict_types=1);

namespace Tests\Modules\X163\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Ui\Pricebook;
use Livewire\Livewire;
use Tests\TestCase;

class PricebookScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-163.pricebook'))->assertOk();

        Livewire::test(Pricebook::class)->assertOk();
    }

    public function test_a_flashed_error_shows_the_error_panel_instead_of_crashing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->withSession(['error' => 'Distinctive failure 4843'])
            ->get(route('x-163.pricebook'))
            ->assertOk()
            ->assertSee("We couldn't load the pricebook")
            ->assertSee('Distinctive failure 4843');
    }
}
