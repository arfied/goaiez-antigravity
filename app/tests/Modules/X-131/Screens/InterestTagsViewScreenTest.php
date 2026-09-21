<?php

declare(strict_types=1);

namespace Tests\Modules\X131\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X131\Actions\InterestInferAction;
use App\Modules\X131\Actions\InterestSetAction;
use App\Modules\X131\Models\PersonInterest;
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

    public function test_can_submit_interest_and_see_on_screen()
    {
        $biz = TestCase::provisionTenant(['name' => 'Interest Tenant']);
        Tenancy::set($biz->id);

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);

        Livewire::test(InterestTagsView::class)
            ->set('personId', (string) $person->id)
            ->set('topic', 'gardening')
            ->call('submit')
            ->assertSet('error', null)
            ->assertSet('success', "Recorded interest 'gardening' for customer #{$person->id}. It is protected from being overwritten by inference. Nothing downstream is wired to it yet.");

        $this->assertDatabaseHas((new PersonInterest)->getTable(), [
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'topic' => 'gardening',
            'is_tenant_set' => true,
        ]);

        $this->actingAs($biz->owner)
            ->withSession(['tenant_id' => $biz->id])
            ->get('/app/x-131/interest-tags')
            ->assertSee('gardening')
            ->assertDontSee('No interests recorded yet');
    }

    public function test_refuses_invalid_input()
    {
        $biz = TestCase::provisionTenant(['name' => 'Interest Tenant']);
        Tenancy::set($biz->id);

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);

        Livewire::test(InterestTagsView::class)
            ->set('personId', '0')
            ->set('topic', 'gardening')
            ->call('submit')
            ->assertSet('error', 'Person ID is required and cannot be 0.');

        Livewire::test(InterestTagsView::class)
            ->set('personId', (string) $person->id)
            ->set('topic', '')
            ->call('submit')
            ->assertSet('error', 'Topic is required.');

        $this->assertDatabaseMissing((new PersonInterest)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }

    public function test_can_infer_interest()
    {
        $biz = TestCase::provisionTenant(['name' => 'Interest Tenant']);
        Tenancy::set($biz->id);

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Sam', 'last_name' => 'Smith']);

        Livewire::test(InterestTagsView::class)
            ->set('inferPersonId', (string) $person->id)
            ->set('inferTopic', 'Snow Removal')
            ->call('inferInterest')
            ->assertSet('error', null)
            ->assertSet('success', "Recorded inferred interest 'Snow Removal' for customer #{$person->id}.");

        $this->assertDatabaseHas((new PersonInterest)->getTable(), [
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'topic' => 'Snow Removal',
            'is_tenant_set' => false,
        ]);

        $this->actingAs($biz->owner)
            ->withSession(['tenant_id' => $biz->id])
            ->get('/app/x-131/interest-tags')
            ->assertSee('Snow Removal')
            ->assertSee('inferred, 80% sure')
            ->assertDontSee('No interests recorded yet');
    }

    public function test_inference_respects_existing_tenant_promise()
    {
        $biz = TestCase::provisionTenant(['name' => 'Interest Tenant']);
        Tenancy::set($biz->id);

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Sam', 'last_name' => 'Smith']);

        Livewire::test(InterestTagsView::class)
            ->set('personId', (string) $person->id)
            ->set('topic', 'Snow Removal')
            ->call('submit');

        Livewire::test(InterestTagsView::class)
            ->set('inferPersonId', (string) $person->id)
            ->set('inferTopic', 'Snow Removal')
            ->call('inferInterest')
            ->assertSet('error', null)
            ->assertSet('success', "The tenant's own wording was kept and the inference was ignored.");

        $this->assertDatabaseHas((new PersonInterest)->getTable(), [
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'topic' => 'Snow Removal',
            'is_tenant_set' => true,
        ]);
    }

    public function test_infer_refuses_invalid_input()
    {
        $biz = TestCase::provisionTenant(['name' => 'Interest Tenant']);
        Tenancy::set($biz->id);

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Sam', 'last_name' => 'Smith']);

        Livewire::test(InterestTagsView::class)
            ->set('inferPersonId', '0')
            ->set('inferTopic', 'Snow Removal')
            ->call('inferInterest')
            ->assertSet('error', 'Person ID is required and cannot be 0.');

        Livewire::test(InterestTagsView::class)
            ->set('inferPersonId', (string) $person->id)
            ->set('inferTopic', '')
            ->call('inferInterest')
            ->assertSet('error', 'Topic is required.');

        $this->assertDatabaseMissing((new PersonInterest)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
