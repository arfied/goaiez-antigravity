<?php

namespace Tests\Modules\X138\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AttributionRowScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-138.attribution-row'))->assertOk();

        Livewire::test(\App\Modules\X138\Ui\AttributionRow::class)->assertOk();
    }
}
