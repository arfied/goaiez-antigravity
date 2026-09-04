<?php

declare(strict_types=1);

namespace Tests\Modules\X142\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X142\Ui\WebhooksView;
use Livewire\Livewire;
use Tests\TestCase;

class WebhooksViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-142.webhooks'))->assertOk();

        Livewire::test(WebhooksView::class)->assertOk();
    }
}
