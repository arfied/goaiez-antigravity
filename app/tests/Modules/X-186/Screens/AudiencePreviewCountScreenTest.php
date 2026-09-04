<?php

declare(strict_types=1);

namespace Tests\Modules\X186\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X186\Ui\AudiencePreviewCount;
use Livewire\Livewire;
use Tests\TestCase;

class AudiencePreviewCountScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-186.audience-preview-count'))->assertOk();

        Livewire::test(AudiencePreviewCount::class)->assertOk();
    }
}
