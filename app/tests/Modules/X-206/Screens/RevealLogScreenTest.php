<?php

declare(strict_types=1);

namespace Tests\Modules\X206\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X206\Models\CredentialReveal;
use App\Modules\X206\Ui\RevealLog;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RevealLogScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-206.reveal-log'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No credential reveal audit records.');

        Tenancy::set((int) $biz->id);
        CredentialReveal::create([
            'business_id' => $biz->id,
            'service_name' => 'distinctive_service_4482',
            'status' => 'permitted',
            'revealed_at' => now(),
        ]);
        Tenancy::forget();

        $this->get(route('x-206.reveal-log'))
            ->assertOk()
            ->assertSee('distinctive_service_4482')
            ->assertSee('[permitted]')
            ->assertDontSee('No credential reveal audit records.');

        Livewire::actingAs($owner)->test(RevealLog::class, ['businessId' => $biz->id])->assertOk();
    }
}
