<?php

namespace Tests\Modules\X220\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PromptHistoryScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-220.prompt-history'))->assertOk();

        Livewire::test(\App\Modules\X220\Ui\PromptHistory::class)->assertOk();
    }
}
