<?php

declare(strict_types=1);

namespace Tests\Modules\X129\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X129\Models\RedirectMap;
use App\Modules\X129\Ui\CutoverQueue;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CutoverQueueScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-129.cutover-queue'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No redirects mapped.');

        Tenancy::setUser($owner->id);
        RedirectMap::create([
            'business_id' => $biz->id,
            'source_url' => '/old-distinctive-4478',
            'destination_url' => '/new-distinctive-4478',
            'status_code' => 301,
            'is_verified' => true,
        ]);
        Tenancy::forget();

        $this->get(route('x-129.cutover-queue'))
            ->assertOk()
            ->assertSee('/old-distinctive-4478')
            ->assertSee('/new-distinctive-4478')
            ->assertSee('[301]')
            ->assertDontSee('No redirects mapped.');

        Livewire::test(CutoverQueue::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-129.cutover-queue.admin'))->assertOk();

        Livewire::test(CutoverQueue::class)->assertOk();
    }

    public function test_can_build_a_redirect(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(CutoverQueue::class)
            ->set('sourceUrl', 'https://old.example.com/pricing')
            ->set('newDomainHost', 'https://new.example.com')
            ->call('buildRedirect')
            ->assertSet('error', null)
            ->assertSet('success', 'Redirect mapped: https://old.example.com/pricing → https://new.example.com/pricing. It is listed below and counted on the migration card; nothing serves these redirects yet.');

        $this->assertDatabaseHas('redirect_maps', [
            'source_url' => 'https://old.example.com/pricing',
            'destination_url' => 'https://new.example.com/pricing',
            'is_verified' => true,
        ]);
    }

    public function test_building_the_same_url_twice_keeps_one_row(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(CutoverQueue::class)
            ->set('sourceUrl', 'https://old.example.com/pricing')
            ->set('newDomainHost', 'https://new.example.com')
            ->call('buildRedirect')
            ->set('sourceUrl', 'https://old.example.com/pricing')
            ->set('newDomainHost', 'https://new.example.com')
            ->call('buildRedirect');

        $this->assertSame(1, RedirectMap::where('business_id', $biz->id)->count());
    }

    public function test_refuses_an_empty_source_url(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(CutoverQueue::class)
            ->set('sourceUrl', '')
            ->set('newDomainHost', 'https://new.example.com')
            ->call('buildRedirect')
            ->assertSet('error', 'Enter the old URL you want redirected.');

        $this->assertDatabaseMissing('redirect_maps', [
            'business_id' => $biz->id,
        ]);

        Livewire::test(CutoverQueue::class)
            ->set('sourceUrl', 'https://old.example.com/pricing')
            ->set('newDomainHost', '   ')
            ->call('buildRedirect')
            ->assertSet('error', 'Enter the new domain host.');

        $this->assertDatabaseMissing('redirect_maps', [
            'business_id' => $biz->id,
        ]);
    }

    public function test_cutover_queue_lists_the_new_redirect(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(CutoverQueue::class)
            ->set('sourceUrl', 'https://old.example.com/pricing')
            ->set('newDomainHost', 'https://new.example.com')
            ->call('buildRedirect');

        Tenancy::forget();

        $this->get(route('x-129.cutover-queue'))
            ->assertOk()
            ->assertSee('https://old.example.com/pricing')
            ->assertDontSee('No redirects mapped.');
    }
}
