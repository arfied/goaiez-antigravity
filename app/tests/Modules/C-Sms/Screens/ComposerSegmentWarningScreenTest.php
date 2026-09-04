<?php

namespace Tests\Modules\CSms\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ComposerSegmentWarningScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-sms.composer-segment-warning'))->assertOk();

        Livewire::test(\App\Modules\CSms\Ui\ComposerSegmentWarning::class)->assertOk();
    }
}
