<?php

declare(strict_types=1);

namespace Tests\Modules\X148\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X148\Ui\RetrievalLatencyEmptyrate;
use Livewire\Livewire;
use Tests\TestCase;

class RetrievalLatencyEmptyrateScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-148.retrieval-latency-emptyrate'))->assertOk();

        Livewire::test(RetrievalLatencyEmptyrate::class)->assertOk();
    }
}
