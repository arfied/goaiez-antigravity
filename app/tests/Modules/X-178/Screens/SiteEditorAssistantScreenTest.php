<?php

declare(strict_types=1);

namespace Tests\Modules\X178\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X178\Ui\SiteEditorAssistant;
use Livewire\Livewire;
use Tests\TestCase;

class SiteEditorAssistantScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-178.site-editor-assistant'))->assertOk();

        Livewire::test(SiteEditorAssistant::class)->assertOk();
    }
}
