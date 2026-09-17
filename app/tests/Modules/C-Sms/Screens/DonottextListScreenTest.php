<?php

declare(strict_types=1);

namespace Tests\Modules\CSms\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CSms\Ui\DonottextList;
use App\Modules\X204\Models\Suppression;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DonottextListScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-sms.donottext-list'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No numbers on the do-not-text list.');

        Tenancy::setUser($owner->id);
        Suppression::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125554610',
            'channel' => 'sms',
            'reason' => 'Distinctive stop 4610',
            'suppressed_at' => now(),
        ]);
        Tenancy::forget();

        $this->get(route('c-sms.donottext-list'))
            ->assertOk()
            ->assertSee('+15125554610')
            ->assertSee('Distinctive stop 4610')
            ->assertDontSee('No numbers on the do-not-text list.');

        Livewire::test(DonottextList::class)->assertOk();
    }
}
