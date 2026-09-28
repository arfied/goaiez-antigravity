<?php

declare(strict_types=1);

namespace Tests\Modules\X138\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X138\Ui\AttributionRow;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AttributionRowScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-138.attribution-row'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Page Earnings</h1>', false);

        Livewire::test(AttributionRow::class)->assertOk();
    }

    public function test_page_earnings_read_the_pixel_mart(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $pageAction = app(PageCreateAction::class);
        $page2 = $pageAction->handle($biz->id, 'services', 'Distinctive services 4472');
        $page2->is_published = true;
        $page2->save();

        measurementPageDay($biz->id, now()->toDateString(), '/services', 37, 2);
        measurementPageDay($biz->id, now()->subDays(40)->toDateString(), '/services', 99);

        $this->get(route('x-138.attribution-row'))
            ->assertOk()
            ->assertSee('Distinctive services 4472')
            ->assertSee('37 views')
            ->assertSee('2 conversions');

        // Fresh tenant with one published page
        $owner2 = User::factory()->create(['role' => UserRole::Owner]);
        $biz2 = $this->provisionTenant(['owner_user_id' => $owner2->id]);
        $this->actingAs($owner2);
        Tenancy::set((int) $biz2->id);

        $page3 = $pageAction->handle($biz2->id, 'home', 'Fresh page');
        $page3->is_published = true;
        $page3->save();

        $this->get(route('x-138.attribution-row'))
            ->assertOk()
            ->assertSee('Not measured yet');
    }
}
