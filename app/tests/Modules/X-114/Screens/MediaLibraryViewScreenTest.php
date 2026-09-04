<?php

namespace Tests\Modules\X114\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MediaLibraryViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-114.media-library'))->assertOk();

        Livewire::test(\App\Modules\X114\Ui\MediaLibraryView::class)->assertOk();
    }
}
