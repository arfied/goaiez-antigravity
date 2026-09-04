<?php

declare(strict_types=1);

namespace Tests\Modules\X186\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X186\Ui\SequenceBuilder;
use Livewire\Livewire;
use Tests\TestCase;

class SequenceBuilderScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-186.sequence-builder'))->assertOk();

        Livewire::test(SequenceBuilder::class)->assertOk();
    }
}
