<?php

declare(strict_types=1);

namespace Tests\Modules\X194\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X194\Ui\SavedViewsList;
use Livewire\Livewire;
use Tests\TestCase;

class SavedViewsListScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-194.saved-views-list'))->assertOk();

        Livewire::test(SavedViewsList::class)->assertOk();
    }
}
