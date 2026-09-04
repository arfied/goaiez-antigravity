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
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-178.site-editor-assistant'))->assertOk();

        Livewire::test(\App\Modules\X178\Ui\SiteEditorAssistant::class)->assertOk();
    }
}
