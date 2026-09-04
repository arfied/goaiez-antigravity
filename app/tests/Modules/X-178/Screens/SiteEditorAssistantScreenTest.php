<?php

namespace Tests\Modules\X178\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SiteEditorAssistantScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-178.site-editor-assistant'))->assertOk();

        Livewire::test(\App\Modules\X178\Ui\SiteEditorAssistant::class)->assertOk();
    }
}
