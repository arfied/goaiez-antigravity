<?php

declare(strict_types=1);

namespace Tests\Modules\CSms\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CSms\Ui\ComposerSegmentWarning;
use Livewire\Livewire;
use Tests\TestCase;

class ComposerSegmentWarningScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-sms.composer-segment-warning'))->assertOk();

        Livewire::test(ComposerSegmentWarning::class)->assertOk();
    }
}
