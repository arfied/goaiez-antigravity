<?php

namespace Tests\Modules\X123\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DlqRequestInspectorScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-123.dlq-request-inspector'))->assertOk();

        Livewire::test(\App\Modules\X123\Ui\DlqRequestInspector::class)->assertOk();
    }
}
