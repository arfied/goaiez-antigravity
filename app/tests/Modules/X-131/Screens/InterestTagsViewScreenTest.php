<?php

declare(strict_types=1);

namespace Tests\Modules\X131\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X131\Ui\InterestTagsView;
use Livewire\Livewire;
use Tests\TestCase;

class InterestTagsViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-131.interest-tags'))->assertOk();

        Livewire::test(InterestTagsView::class)->assertOk();
    }
}
