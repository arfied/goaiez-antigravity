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
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-211.invoice-thread-beside'))->assertOk();

        Livewire::test(\App\Modules\X211\Ui\InvoiceThreadBeside::class)->assertOk();
    }
}
