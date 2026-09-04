<?php

namespace Tests\Modules\CWhatsapp\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class TemplateStatusCardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-whatsapp.template-status-card'))->assertOk();

        Livewire::test(\App\Modules\CWhatsapp\Ui\TemplateStatusCard::class)->assertOk();
    }
}
