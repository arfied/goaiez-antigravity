<?php

namespace Tests\Modules\CMail\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DnsCardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-mail.dns-card'))->assertOk();

        Livewire::test(\App\Modules\CMail\Ui\DnsCard::class)->assertOk();
    }
}
