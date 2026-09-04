<?php

namespace Tests\Modules\X186\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AudiencePreviewCountScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-186.audience-preview-count'))->assertOk();

        Livewire::test(\App\Modules\X186\Ui\AudiencePreviewCount::class)->assertOk();
    }
}
