<?php

declare(strict_types=1);

namespace Tests\Modules\CWhatsapp\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CWhatsapp\Ui\TemplateApprovalQueue;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateApprovalQueueScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-whatsapp.template-approval-queue'))->assertOk();

        Livewire::test(TemplateApprovalQueue::class)->assertOk();
    }
}
