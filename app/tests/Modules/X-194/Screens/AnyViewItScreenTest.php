<?php

namespace Tests\Modules\X194\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AnyViewItScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-194.any-view-it'))->assertOk();

        Livewire::test(\App\Modules\X194\Ui\AnyViewIt::class)->assertOk();
    }
}
