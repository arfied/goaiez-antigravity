<?php

declare(strict_types=1);

namespace Tests\Modules\X139\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X139\Models\ConversionUpload;
use App\Modules\X139\Ui\RejectionRate;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RejectionRateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-139.rejection-rate'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Rejected Conversions</h1>', false);

        Livewire::test(RejectionRate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-139.rejection-rate.admin'))->assertOk();

        Livewire::test(RejectionRate::class)->assertOk();
    }

    public function test_component_shows_rate_and_reasons(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        // Empty state
        Livewire::test(RejectionRate::class, ['businessId' => $biz->id])
            ->assertSee('Rejected Conversions: 0 (0.0% Rate)');

        Tenancy::actingAs($biz->id, function () use ($biz) {
            ConversionUpload::forceCreate([
                'business_id' => $biz->id,
                'job_id' => 101,
                'conversion_value_cents' => 10000,
                'gclid_or_fbc' => 'gclid_1',
                'status' => 'uploaded',
            ]);
            ConversionUpload::forceCreate([
                'business_id' => $biz->id,
                'job_id' => 102,
                'conversion_value_cents' => 10000,
                'gclid_or_fbc' => 'gclid_2',
                'status' => 'rejected',
                'rejection_reason' => 'Invalid click ID',
            ]);
            ConversionUpload::forceCreate([
                'business_id' => $biz->id,
                'job_id' => 103,
                'conversion_value_cents' => 10000,
                'gclid_or_fbc' => 'gclid_3',
                'status' => 'rejected',
                'rejection_reason' => 'Conversion too old',
            ]);
        });

        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(RejectionRate::class, ['businessId' => $biz->id])
            ->assertSee('Rejected Conversions: 2 (66.7% Rate)')
            ->assertSee('Invalid click ID')
            ->assertSee('Conversion too old');
    }
}
