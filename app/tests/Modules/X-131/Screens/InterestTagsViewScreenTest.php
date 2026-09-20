<?php

declare(strict_types=1);

namespace Tests\Modules\X131\Screens;

use App\Modules\X121\Models\Person;
use App\Modules\X131\Models\PersonInterest;
use App\Modules\X131\Ui\InterestTagsView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class InterestTagsViewScreenTest extends TestCase
{
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

        $this->assertDatabaseHas((new PersonInterest())->getTable(), [
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

        $this->assertDatabaseMissing((new PersonInterest())->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
