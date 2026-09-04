<?php

namespace Tests\Modules\X211\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class InvoiceThreadBesideScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-211.invoice-thread-beside'))->assertOk();

        Livewire::test(\App\Modules\X211\Ui\InvoiceThreadBeside::class)->assertOk();
    }
}
