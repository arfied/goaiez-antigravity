<?php

namespace Tests\Modules\X211\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CollectionsPackagePreviewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-211.collections-package-preview'))->assertOk();

        Livewire::test(\App\Modules\X211\Ui\CollectionsPackagePreview::class)->assertOk();
    }
}
