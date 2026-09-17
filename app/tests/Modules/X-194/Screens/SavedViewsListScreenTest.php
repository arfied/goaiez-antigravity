<?php

declare(strict_types=1);

namespace Tests\Modules\X194\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X194\Actions\ViewSaveAction;
use App\Modules\X194\Ui\SavedViewsList;
use Livewire\Livewire;
use Tests\TestCase;

class SavedViewsListScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-194.saved-views-list'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('You have not saved a view yet.');

        app(ViewSaveAction::class)->save($biz->id, 'Distinctive GET View 4604');

        $this->get(route('x-194.saved-views-list'))
            ->assertOk()
            ->assertSee('Distinctive GET View 4604')
            ->assertDontSee('You have not saved a view yet.');

        Livewire::test(SavedViewsList::class)->assertOk();
    }
}
