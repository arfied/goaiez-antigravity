<?php

declare(strict_types=1);

namespace Tests\Modules\X178\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X178\Models\DesignChange;
use App\Modules\X178\Ui\SiteEditorAssistant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        $page = app(PageCreateAction::class)->handle((int) $biz->id, 'test-slug', 'Test Page');

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

    public function test_can_undo_a_design_change(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $page = app(PageCreateAction::class)->handle((int) $biz->id, 'test-slug', 'Test Page');

        Livewire::test(SiteEditorAssistant::class)
            ->set('pageId', (string) $page->id)
            ->set('niche', 'plumbing')
            ->call('generate')
            ->assertSet('error', '');

        $change = DesignChange::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(SiteEditorAssistant::class)
            ->call('undoChange', $change->id)
            ->assertSet('undoSuccess', 'Change '.$change->block_ref.' is marked undone. Nothing downstream reacts to an undo yet, and no earlier version is restored.');

        $this->assertDatabaseHas((new DesignChange)->getTable(), [
            'id' => $change->id,
            'status' => 'undone',
        ]);
    }

    public function test_the_list_shows_the_change_as_undone(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $page = app(PageCreateAction::class)->handle((int) $biz->id, 'test-slug', 'Test Page');

        Livewire::test(SiteEditorAssistant::class)
            ->set('pageId', (string) $page->id)
            ->set('niche', 'plumbing')
            ->call('generate');

        $change = DesignChange::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(SiteEditorAssistant::class)
            ->call('undoChange', $change->id);

        Tenancy::forget();

        $this->get(route('x-178.site-editor-assistant'))
            ->assertOk()
            ->assertSee('undone')
            ->assertDontSee('applied');
    }

    public function test_the_undo_button_disappears_once_undone(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $page = app(PageCreateAction::class)->handle((int) $biz->id, 'test-slug', 'Test Page');

        Livewire::test(SiteEditorAssistant::class)
            ->set('pageId', (string) $page->id)
            ->set('niche', 'plumbing')
            ->call('generate');

        $change = DesignChange::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(SiteEditorAssistant::class)
            ->call('undoChange', $change->id);

        Tenancy::forget();

        $this->get(route('x-178.site-editor-assistant'))
            ->assertOk()
            ->assertDontSee('undoChange(');
    }

    public function test_undoing_another_tenants_change_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $pageB = app(PageCreateAction::class)->handle((int) $bizB->id, 'test-slug', 'Test Page');
        Livewire::test(SiteEditorAssistant::class)
            ->set('pageId', (string) $pageB->id)
            ->set('niche', 'plumbing')
            ->call('generate');
        $changeB = DesignChange::where('business_id', $bizB->id)->firstOrFail();

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(SiteEditorAssistant::class)->call('undoChange', $changeB->id);
    }
}
