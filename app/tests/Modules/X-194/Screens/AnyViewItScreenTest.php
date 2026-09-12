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
}
