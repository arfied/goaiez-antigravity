<?php

declare(strict_types=1);

namespace Tests\Modules\X179\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X179\Actions\ContentExtractAction;
use App\Modules\X179\Actions\TemplateMatchAction;
use App\Modules\X179\Ui\MatchScores;
use Livewire\Livewire;
use Tests\TestCase;

class MatchScoresScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = new ContentExtractAction;
        $action->extractContent($biz->id, 123, 'gbp', 'test');
        $matchAction = new TemplateMatchAction;
        $matchAction->matchAndRender($biz->id, 123);
        $prospectId = 123;

        $this->get(route('x-179.match-scores', ['prospectId' => $prospectId]))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(MatchScores::class, ['prospectId' => $prospectId])->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $action = new ContentExtractAction;
        $action->extractContent($biz->id, 123, 'gbp', 'test');
        $matchAction = new TemplateMatchAction;
        $matchAction->matchAndRender($biz->id, 123);
        $prospectId = 123;

        $this->get(route('x-179.match-scores.admin', ['prospectId' => $prospectId]))->assertOk();

        Livewire::test(MatchScores::class, ['prospectId' => $prospectId])->assertOk();
    }
}
