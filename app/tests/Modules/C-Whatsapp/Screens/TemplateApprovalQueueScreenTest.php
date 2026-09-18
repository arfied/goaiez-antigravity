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
}
