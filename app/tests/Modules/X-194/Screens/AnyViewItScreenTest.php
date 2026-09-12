<?php

declare(strict_types=1);

namespace Tests\Modules\X194\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X194\Actions\ViewSaveAction;
use App\Modules\X194\Ui\AnyViewIt;
use Livewire\Livewire;
use Tests\TestCase;

class AnyViewItScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-194.any-view-it'))->assertOk();

        Livewire::test(AnyViewIt::class)->assertOk();
    }

    /**
     * Proves a signed-in tenant can reach a saved view on the initial GET request via query string viewId.
     */
    public function test_screen_shows_view_on_real_request(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $view = app(ViewSaveAction::class)->save($biz->id, 'My Initial GET View');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id]))
            ->assertOk()
            ->assertSee('My Initial GET View');
    }

    /**
     * Proves locationTimezone can be sourced from the query string.
     */
    public function test_location_timezone_from_url(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $view = app(ViewSaveAction::class)->save($biz->id, 'TZ View');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id, 'locationTimezone' => 'America/Chicago']))
            ->assertOk()
            ->assertSee('America/Chicago');
    }

    /**
     * Proves the job count tile is hidden when zero, as nothing in production supplies it.
     */
    public function test_job_count_tile_hidden_when_zero(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $view = app(ViewSaveAction::class)->save($biz->id, 'Count View');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id]))
            ->assertOk()
            ->assertDontSee('<h4>Count</h4>', false);
    }
}
