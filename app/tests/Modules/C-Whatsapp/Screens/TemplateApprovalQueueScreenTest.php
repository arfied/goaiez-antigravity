<?php

declare(strict_types=1);

namespace Tests\Modules\CWhatsapp\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Modules\CWhatsapp\Ui\TemplateApprovalQueue;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateApprovalQueueScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-whatsapp.template-approval-queue'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing waiting for approval.');

        Tenancy::setUser($owner->id);
        WhatsappTemplate::create([
            'business_id' => $biz->id,
            'name' => 'distinctive_template_4647',
            'body_text' => 'Distinctive body 4647',
            'status' => 'pending_approval',
        ]);
        Tenancy::forget();

        $this->get(route('c-whatsapp.template-approval-queue'))
            ->assertOk()
            ->assertSee('distinctive_template_4647')
            ->assertDontSee('Nothing waiting for approval.');

        Livewire::test(TemplateApprovalQueue::class)->assertOk();
    }

    public function test_control_submits_template_and_updates_screens(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. empty state
        $this->get(route('c-whatsapp.template-approval-queue'))
            ->assertSee('Nothing waiting for approval.');

        $this->get(route('c-whatsapp.template-status-card'))
            ->assertSee('No templates yet.');

        // 2. drive control
        Livewire::test(TemplateApprovalQueue::class)
            ->set('name', 'my_template')
            ->set('category', 'marketing')
            ->set('bodyText', 'Hello world')
            ->call('submit')
            ->assertSet('success', 'Submitted template my_template.')
            ->assertSet('name', '')
            ->assertSet('category', '')
            ->assertSet('bodyText', '');

        // 3. assert row exists
        $this->assertDatabaseHas((new WhatsappTemplate)->getTable(), [
            'business_id' => $biz->id,
            'name' => 'my_template',
            'category' => 'marketing',
            'body_text' => 'Hello world',
        ]);

        // 4. GET control's screen and assert new value is visible
        $this->get(route('c-whatsapp.template-approval-queue'))
            ->assertSee('my_template')
            ->assertDontSee('Nothing waiting for approval.');

        // 5. GET one of the other screens it feeds and assert it is no longer empty
        $this->get(route('c-whatsapp.template-status-card'))
            ->assertSee('my_template')
            ->assertDontSee('No templates yet.');
    }

    public function test_control_refuses_invalid_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(TemplateApprovalQueue::class)
            ->set('name', '')
            ->set('category', 'marketing')
            ->set('bodyText', 'Hello world')
            ->call('submit')
            ->assertSet('error', 'Name is required.');

        $this->assertDatabaseMissing((new WhatsappTemplate)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
