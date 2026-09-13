<?php

declare(strict_types=1);

namespace Tests\Modules\X183\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X183\Actions\ContentGateAction;
use App\Modules\X183\Actions\ContentWriteAction;
use App\Modules\X183\Ui\DraftReview;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DraftReviewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-183.draft-review'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No drafts yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        $published = app(ContentWriteAction::class)->writeDraft(
            businessId: (int) $biz->id,
            title: 'Five signs your furnace needs a tune-up',
            bodyText: 'A furnace that short-cycles or smells of dust is worth a visit before the cold arrives.',
        );
        app(ContentWriteAction::class)->writeDraft(
            businessId: (int) $biz->id,
            title: 'Why we test every water heater',
            bodyText: 'Every visit ends with a pressure test, because a relief valve fails without warning.',
        );
        $heldBack = app(ContentWriteAction::class)->writeDraft(
            businessId: (int) $biz->id,
            title: 'Tune-up prices for October',
            bodyText: 'A full tune-up is $XX this month.',
        );
        app(ContentGateAction::class)->evaluateGate((int) $biz->id, (int) $published->id);
        app(ContentGateAction::class)->evaluateGate((int) $biz->id, (int) $heldBack->id);
        Tenancy::forget();

        $this->get(route('x-183.draft-review'))
            ->assertOk()
            ->assertSee('Five signs your furnace needs a tune-up · Published')
            ->assertSee('Why we test every water heater · Not checked yet')
            ->assertSee('Tune-up prices for October · Held back')
            ->assertDontSee('No drafts yet');

        Livewire::test(DraftReview::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-183.draft-review.admin'))->assertOk();

        Livewire::test(DraftReview::class)->assertOk();
    }
}
