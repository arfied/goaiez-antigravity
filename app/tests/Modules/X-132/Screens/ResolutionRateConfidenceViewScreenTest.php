<?php

declare(strict_types=1);

namespace Tests\Modules\X132\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X132\Models\PersonLink;
use App\Modules\X132\Ui\ResolutionRateConfidenceView;
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
}
