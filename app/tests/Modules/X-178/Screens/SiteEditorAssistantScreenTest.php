<?php

declare(strict_types=1);

namespace Tests\Modules\X178\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X178\Models\DesignChange;
use App\Modules\X178\Ui\SiteEditorAssistant;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SiteEditorAssistantScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-178.site-editor-assistant'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No design changes yet.');

        Tenancy::setUser($owner->id);
        DesignChange::create([
            'business_id' => $biz->id,
            'page_id' => 4631,
            'change_type' => 'color_token',
            'block_ref' => 'distinctive_block_4631',
            'previous_state' => ['color' => '#111111'],
            'new_state' => ['color' => '#0b3d2e'],
            'contrast_ratio' => 7.5,
            'status' => 'applied',
        ]);
        Tenancy::forget();

        $this->get(route('x-178.site-editor-assistant'))
            ->assertOk()
            ->assertSee('distinctive_block_4631')
            ->assertSee('color_token')
            ->assertSee('contrast 7.5:1')
            ->assertDontSee('No design changes yet.');

        Livewire::test(SiteEditorAssistant::class)->assertOk();
    }

    public function test_generate_control(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $page = app(\App\Modules\X103\Actions\PageCreateAction::class)->handle((int) $biz->id, 'test-slug', 'Test Page');

        $test = Livewire::test(SiteEditorAssistant::class)
            ->set('pageId', (string) $page->id)
            ->set('niche', 'plumbing')
            ->call('generate')
            ->assertSet('error', '');

        $successMessage = $test->get('success');
        $this->assertStringContainsString('Generated form. It places a lead-capture block and invents no price. Block ref: block_form_lead_capture_', $successMessage);

        $this->assertDatabaseHas((new DesignChange)->getTable(), [
            'page_id' => $page->id,
            'change_type' => 'form_gen',
        ]);

        $this->get(route('x-178.site-editor-assistant'))
            ->assertOk()
            ->assertSee('form_gen')
            ->assertDontSee('No design changes yet.');

        Livewire::test(SiteEditorAssistant::class)
            ->set('pageId', '0')
            ->call('generate')
            ->assertSet('error', 'Page ID must be provided and cannot be 0.');
    }
}
