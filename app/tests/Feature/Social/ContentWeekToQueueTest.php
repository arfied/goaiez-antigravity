<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X182\Ui\SocialQueue;
use App\Modules\X184\Actions\PlanProposeAction;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

it('shows the link only for facebook or instagram and prefills the topic', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);

    Tenancy::set((int) $biz->id);
    app(PlanProposeAction::class)->proposePlan(
        businessId: (int) $biz->id,
        weekLabel: 'Week 7401',
        postsCadence: 3,
        items: [
            ['source_event' => 'season.turned', 'topic_theme' => 'Winter prep 7401', 'channel' => 'facebook'],
            ['source_event' => 'season.turned', 'topic_theme' => 'Listing refresh 7402', 'channel' => 'gbp'],
        ]
    );

    $this->actingAs($owner);

    $this->get(route('x-184.content-week'))
        ->assertOk()
        ->assertSee('Write this post')
        ->assertSee(route('x-182.social-queue', ['topic' => 'Winter prep 7401']), false)
        ->assertDontSee(route('x-182.social-queue', ['topic' => 'Listing refresh 7402']), false);

    Livewire::withQueryParams(['topic' => 'Winter prep 7401'])
        ->test(SocialQueue::class)
        ->assertSet('content', 'Winter prep 7401');
});

it('does not prefill content when topic is missing', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);

    Tenancy::set((int) $biz->id);

    Livewire::test(SocialQueue::class)
        ->assertSet('content', '');
});
