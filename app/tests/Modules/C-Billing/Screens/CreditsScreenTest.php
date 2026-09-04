<?php

namespace Tests\Modules\CBilling\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CreditsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-billing.credits'))->assertOk();

        Livewire::test(\App\Modules\CBilling\Ui\Credits::class)->assertOk();
    }
}
