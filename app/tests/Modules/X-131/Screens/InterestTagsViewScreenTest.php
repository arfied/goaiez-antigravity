<?php

declare(strict_types=1);

namespace Tests\Modules\X131\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X131\Actions\InterestInferAction;
use App\Modules\X131\Actions\InterestSetAction;
use App\Modules\X131\Ui\InterestTagsView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class InterestTagsViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-131.interest-tags'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No interests recorded yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);

        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);
        app(InterestSetAction::class)->set((int) $biz->id, (int) $dana->id, 'Commercial HVAC Maintenance');
        app(InterestInferAction::class)->infer(businessId: (int) $biz->id, personId: (int) $dana->id, topic: 'Duct Cleaning', confidenceScore: 0.72, source: 'page_view_scroll_depth');

        Tenancy::forget();

        $this->get(route('x-131.interest-tags'))
            ->assertSee('Dana Whitfield')
            ->assertSee('Commercial HVAC Maintenance')
            ->assertSee('set by you')
            ->assertSee('Duct Cleaning')
            ->assertSee('inferred, 72% sure')
            ->assertDontSee('No interests recorded yet');

        Livewire::test(InterestTagsView::class, ['businessId' => $biz->id])->assertOk();
    }
}
