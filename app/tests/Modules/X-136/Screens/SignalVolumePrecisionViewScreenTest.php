<?php

declare(strict_types=1);

namespace Tests\Modules\X136\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X136\Ui\SignalVolumePrecisionView;
use Livewire\Livewire;
use Tests\TestCase;

class SignalVolumePrecisionViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-136.signal-volume-precision'))->assertOk();

        Livewire::test(SignalVolumePrecisionView::class)->assertOk();
    }
}
