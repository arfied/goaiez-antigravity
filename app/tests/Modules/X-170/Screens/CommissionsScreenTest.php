<?php

namespace Tests\Modules\X170\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CommissionsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-170.commissions'))->assertOk();

        Livewire::test(\App\Modules\X170\Ui\Commissions::class)->assertOk();
    }
}
