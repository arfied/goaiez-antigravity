<?php

declare(strict_types=1);

namespace Tests\Modules\X154\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X154\Ui\ReadbackScreen;
use Livewire\Livewire;
use Tests\TestCase;

class ReadbackScreenScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-154.readback-screen'))->assertOk();

        Livewire::test(ReadbackScreen::class)->assertOk();
    }

    public function test_can_create_lexicon_mapping(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ReadbackScreen::class)
            ->set('genericTerm', 'plumber')
            ->set('preferredTerm', 'drain specialist')
            ->set('category', 'service_name')
            ->call('setMapping')
            ->assertSet('success', 'Recorded vocabulary mapping: plumber to drain specialist. An existing entry for this generic term was updated if it existed. Nothing downstream is wired yet.');

        $this->assertDatabaseHas('tenant_lexicons', [
            'business_id' => $biz->id,
            'generic_term' => 'plumber',
            'preferred_term' => 'drain specialist',
        ]);

        $this->get(route('x-154.readback-screen'))
            ->assertSee('drain specialist');
    }

    public function test_refuses_empty_term(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ReadbackScreen::class)
            ->set('genericTerm', '')
            ->call('setMapping')
            ->assertSet('error', 'Please provide all terms to map.');

        $this->assertDatabaseMissing('tenant_lexicons', [
            'business_id' => $biz->id,
        ]);
    }

    public function test_can_preview_readback(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ReadbackScreen::class)
            ->set('genericTerm', 'plumber')
            ->set('preferredTerm', 'drain specialist')
            ->set('category', 'service_name')
            ->call('setMapping');

        Livewire::test(ReadbackScreen::class)
            ->set('templateText', 'The plumber will see the patient now.')
            ->call('previewReadback')
            ->assertSet('preview', 'The drain specialist will see the service now.');
    }

    public function test_refuses_empty_preview_text(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ReadbackScreen::class)
            ->set('templateText', '')
            ->call('previewReadback')
            ->assertSet('error', 'Please provide text to preview.')
            ->assertSet('preview', null);
    }
}
