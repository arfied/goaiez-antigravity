<?php

namespace Tests\Modules\X194\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SavedViewsListScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-194.saved-views-list'))->assertOk();

        Livewire::test(\App\Modules\X194\Ui\SavedViewsList::class)->assertOk();
    }
}
