<?php

declare(strict_types=1);

namespace Tests\Modules\X109\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X109\Ui\SubmissionLog;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionLogScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-109.submission-log'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSeeText('No contact forms have been submitted for this tenant.');

        Livewire::test(SubmissionLog::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-109.submission-log.admin'))->assertOk();

        Livewire::test(SubmissionLog::class)->assertOk();
    }
}
