<?php

declare(strict_types=1);

namespace Tests\Modules\X132\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X132\Models\ResolutionEvidence;
use App\Modules\X132\Ui\PersonTimelineView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PersonTimelineViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-132.person-timeline'))
            ->assertOk()
            ->assertSee('Person timeline')
            ->assertSee('No evidence found');

        ResolutionEvidence::create([
            'business_id' => $biz->id,
            'canonical_person_id' => 123,
            'field_name' => 'email',
            'field_value' => 'distinctive_value_xyz',
            'source_provider' => 'email_match',
            'confidence_rate' => 1.000,
        ]);

        Livewire::test(PersonTimelineView::class, ['personId' => 123])
            ->assertOk()
            ->assertSee('distinctive_value_xyz');
    }

    public function test_403_for_other_roles(): void
    {
        $tech = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($tech);

        $this->get(route('x-132.person-timeline'))->assertForbidden();
        Livewire::test(PersonTimelineView::class)->assertForbidden();
    }

    public function test_can_record_evidence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        $p1 = app(PersonLookupAction::class)->create((int) $biz->id, ['first_name' => 'Ada', 'email' => 'ada@example.com']);

        Livewire::test(PersonTimelineView::class)
            ->set('personPick', $p1)
            ->set('fieldName', 'email')
            ->set('fieldValue', 'ada.lovelace@example.com')
            ->call('recordEvidence')
            ->assertSet('success', 'Evidence recorded successfully.')
            ->assertSee('ada.lovelace@example.com');

        $this->assertDatabaseHas((new ResolutionEvidence)->getTable(), [
            'business_id' => $biz->id,
            'canonical_person_id' => $p1,
            'field_name' => 'email',
            'field_value' => 'ada.lovelace@example.com',
        ]);

        Tenancy::forget();

        $this->get(route('x-132.person-timeline'))->assertOk();
    }

    public function test_refuses_empty_person(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        Livewire::test(PersonTimelineView::class)
            ->set('fieldValue', 'ada.lovelace@example.com')
            ->call('recordEvidence')
            ->assertSet('error', 'Please select a person.');

        $this->assertDatabaseMissing((new ResolutionEvidence)->getTable(), [
            'business_id' => $biz->id,
        ]);
        Tenancy::forget();
    }

    public function test_refuses_empty_field_value(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        $p1 = app(PersonLookupAction::class)->create((int) $biz->id, ['first_name' => 'Ada', 'email' => 'ada@example.com']);

        Livewire::test(PersonTimelineView::class)
            ->set('personPick', $p1)
            ->set('fieldValue', '   ')
            ->call('recordEvidence')
            ->assertSet('error', 'Field value cannot be empty.');

        $this->assertDatabaseMissing((new ResolutionEvidence)->getTable(), [
            'business_id' => $biz->id,
        ]);
        Tenancy::forget();
    }
}
