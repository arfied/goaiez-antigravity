<?php

declare(strict_types=1);

namespace Tests\Modules\X132\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X132\Models\PersonLink;
use App\Modules\X132\Ui\ResolutionRateConfidenceView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ResolutionRateConfidenceViewScreenTest extends TestCase
{
    public function test_screen_renders_empty_and_filled_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-132.resolution-rate-confidence'))
            ->assertOk()
            ->assertSee('Resolution rate & confidence')
            ->assertSee('No links found.');

        PersonLink::create([
            'business_id' => $biz->id,
            'canonical_person_id' => 101,
            'linked_person_id' => 102,
            'confidence_rate' => 0.8877,
        ]);

        Livewire::test(ResolutionRateConfidenceView::class)
            ->assertOk()
            ->assertSee('0.888');
    }

    public function test_403_for_other_roles(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Staff]);
        $this->provisionTenant(['owner_user_id' => $employee->id]);
        $this->actingAs($employee);

        $this->get(route('x-132.resolution-rate-confidence'))
            ->assertForbidden();

        Livewire::test(ResolutionRateConfidenceView::class)
            ->assertForbidden();
    }

    public function test_can_link_people(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        $p1 = app(PersonLookupAction::class)->create((int) $biz->id, ['first_name' => 'Ada', 'email' => 'ada@example.com']);
        $p2 = app(PersonLookupAction::class)->create((int) $biz->id, ['first_name' => 'Grace', 'email' => 'grace@example.com']);

        Livewire::test(ResolutionRateConfidenceView::class)
            ->set('canonicalId', $p1)
            ->set('duplicateId', $p2)
            ->call('linkPeople')
            ->assertSet('success', 'The two people are now recorded as the same person. This records the link only and does not delete or alter the duplicate person.');

        $this->assertDatabaseHas((new PersonLink)->getTable(), [
            'business_id' => $biz->id,
            'canonical_person_id' => $p1,
            'linked_person_id' => $p2,
        ]);

        $link = PersonLink::where('business_id', $biz->id)->first();

        Tenancy::forget();

        $this->get(route('x-132.resolution-rate-confidence'))
            ->assertOk()
            ->assertSee((string) $link->id);
    }

    public function test_refuses_empty_ids(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        Livewire::test(ResolutionRateConfidenceView::class)
            ->call('linkPeople')
            ->assertSet('error', 'Please select both a canonical person and a duplicate person.');

        $this->assertDatabaseMissing((new PersonLink)->getTable(), [
            'business_id' => $biz->id,
        ]);
        Tenancy::forget();
    }

    public function test_refuses_linking_to_self(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        $p1 = app(PersonLookupAction::class)->create((int) $biz->id, ['first_name' => 'Ada', 'email' => 'ada@example.com']);

        Livewire::test(ResolutionRateConfidenceView::class)
            ->set('canonicalId', $p1)
            ->set('duplicateId', $p1)
            ->call('linkPeople')
            ->assertSet('error', 'Cannot link a person to themselves.');

        $this->assertDatabaseMissing((new PersonLink)->getTable(), [
            'business_id' => $biz->id,
        ]);
        Tenancy::forget();
    }
}
