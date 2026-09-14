<?php

declare(strict_types=1);

namespace Tests\Modules\X132\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X132\Models\ResolutionEvidence;
use App\Modules\X132\Ui\PersonTimelineView;
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
}
