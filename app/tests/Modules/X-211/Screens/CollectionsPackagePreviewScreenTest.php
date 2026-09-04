<?php

declare(strict_types=1);

namespace Tests\Modules\X211\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X211\Ui\CollectionsPackagePreview;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionsPackagePreviewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-211.collections-package-preview'))->assertOk();

        Livewire::test(CollectionsPackagePreview::class)->assertOk();
    }
}
