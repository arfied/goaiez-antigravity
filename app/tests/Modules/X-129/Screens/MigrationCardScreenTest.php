<?php

declare(strict_types=1);

namespace Tests\Modules\X129\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X129\Models\RedirectMap;
use App\Modules\X129\Ui\MigrationCard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class MigrationCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $response = $this->get(route('x-129.migration-card'));
        if ($response->status() !== 200) {
            file_put_contents('/tmp/error.html', $response->getContent());
        }

        $response->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No redirects mapped yet.');

        Tenancy::setUser($owner->id);
        RedirectMap::create([
            'business_id' => $biz->id,
            'source_url' => '/old-distinctive-4483',
            'destination_url' => '/new-distinctive-4483',
            'status_code' => 301,
            'is_verified' => true
        ]);
        Tenancy::forget();

        $this->get(route('x-129.migration-card'))
            ->assertOk()
            ->assertSee('Verified redirects: 1 of 1')
            ->assertDontSee('No redirects mapped yet.');

        Livewire::test(MigrationCard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-129.migration-card.admin'))->assertOk();

        Livewire::test(MigrationCard::class)->assertOk();
    }
}
