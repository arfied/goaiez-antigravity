<?php

declare(strict_types=1);

namespace Tests\Modules\X123\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X123\Ui\DlqRequestInspector;
use Livewire\Livewire;
use Tests\TestCase;

class DlqRequestInspectorScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-123.dlq-request-inspector'))->assertOk();

        Livewire::test(DlqRequestInspector::class)->assertOk();
    }
}
