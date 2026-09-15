<?php

declare(strict_types=1);

namespace Tests\Modules\X207\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X207\Models\PushPrompt;
use App\Modules\X207\Ui\PromptcopyEditor;
use Livewire\Livewire;
use Tests\TestCase;

class PromptcopyEditorScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // Empty state
        $this->get(route('x-207.promptcopy-editor'))
            ->assertOk()
            ->assertSee('Push Prompt Copy Editor')
            ->assertSee('No prompt templates configured.');

        // Seed distinctive PushPrompt
        PushPrompt::forceCreate([
            'business_id' => $biz->id,
            'prompt_title' => 'Distinctive Push Prompt Title',
            'prompt_body' => 'Distinctive body',
        ]);

        // List prompts
        $this->get(route('x-207.promptcopy-editor'))
            ->assertOk()
            ->assertSee('Distinctive Push Prompt Title');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-207.promptcopy-editor.admin'))->assertOk();

        Livewire::test(PromptcopyEditor::class)->assertOk();
    }

    public function test_screen_403_for_other_roles(): void
    {
        $tech = User::factory()->create(['role' => UserRole::Staff]);
        $biz = $this->provisionTenant();
        $this->actingAs($tech);

        $this->get(route('x-207.promptcopy-editor'))->assertForbidden();

        Livewire::test(PromptcopyEditor::class)
            ->assertForbidden();
    }
}
