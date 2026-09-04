<?php

declare(strict_types=1);

namespace Tests\Modules\X114\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X114\Ui\MediaLibraryView;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-114.media-library'))->assertOk();

        Livewire::test(MediaLibraryView::class)->assertOk();
    }
}
