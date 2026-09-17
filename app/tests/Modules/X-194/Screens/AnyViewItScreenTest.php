<?php

declare(strict_types=1);

namespace Tests\Modules\X194\Screens;

use App\Enums\UserRole;
use App\Models\Location;
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

        // Give location a timezone so it renders
        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'America/Chicago';
        $location->save();

        $this->get(route('x-194.any-view-it'))->assertOk()
            ->assertSee('Your account')->assertDontSee('Internal Platform Console')->assertDontSee('this screen is planned in')->assertSee('No view selected');

        Livewire::test(AnyViewIt::class)->assertOk();
    }

    public function test_screen_shows_view_on_real_request(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'America/Chicago';
        $location->save();

        $view = app(ViewSaveAction::class)->save($biz->id, 'Distinctive GET View 4611');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id]))
            ->assertOk()
            ->assertSee('Distinctive GET View 4611')
            ->assertSee('Your account')->assertDontSee('No view selected');
    }

    public function test_job_count_tile_hidden_when_zero(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'America/Chicago';
        $location->save();

        $view = app(ViewSaveAction::class)->save($biz->id, 'Count View');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id]))
            ->assertOk()
            ->assertDontSee('<h4>Count</h4>', false);
    }

    public function test_screen_shows_location_timezone(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = 'Asia/Tokyo';
        $location->save();

        $view = app(ViewSaveAction::class)->save($biz->id, 'Tokyo View');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id]))
            ->assertOk()
            ->assertSee('Asia/Tokyo');
    }

    public function test_screen_refuses_when_no_location(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Location::where('business_id', $biz->id)->delete();

        $view = app(ViewSaveAction::class)->save($biz->id, 'No Loc View');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id]))
            ->assertOk()
            ->assertSee('Your account has no location to render the view in.');
    }

    public function test_screen_refuses_when_timezone_null(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $location = Location::where('business_id', $biz->id)->first();
        $location->timezone = null;
        $location->save();

        $view = app(ViewSaveAction::class)->save($biz->id, 'Null TZ View');

        $this->get(route('x-194.any-view-it', ['viewId' => $view->id]))
            ->assertOk()
            ->assertSee('The location has no timezone set.');
    }
}
