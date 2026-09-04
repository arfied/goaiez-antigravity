<?php

declare(strict_types=1);

namespace Tests\Modules\X212\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X212\Ui\DryrunPreview;
use Livewire\Livewire;
use Tests\TestCase;

class DryrunPreviewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-212.dryrun-preview'))->assertOk();

        Livewire::test(DryrunPreview::class)->assertOk();
    }
}
