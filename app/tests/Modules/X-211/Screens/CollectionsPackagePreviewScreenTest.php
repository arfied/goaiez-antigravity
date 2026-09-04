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
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-211.collections-package-preview'))->assertOk();

        Livewire::test(\App\Modules\X211\Ui\CollectionsPackagePreview::class)->assertOk();
    }
}
