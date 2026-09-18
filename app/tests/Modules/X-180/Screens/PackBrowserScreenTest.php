<?php

declare(strict_types=1);

namespace Tests\Modules\X180\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X180\Actions\PackSeedAction;
use App\Modules\X180\Ui\PackBrowser;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PackBrowserScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-180.pack-browser'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No starter pack has been seeded for you yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        $r = app(PackSeedAction::class)->seed(
            (int) $biz->id,
            'HVAC Starter Pack',
            'hvac',
            2,
            [
                ['title' => 'Furnace tune-up reminder', 'license_source' => 'Goaiez originals'],
                ['title' => 'Spring AC checklist', 'license_source' => 'Goaiez originals'],
            ]
        );
        $this->assertSame('seeded', $r['status']);
        Tenancy::forget();

        $this->get(route('x-180.pack-browser'))
            ->assertSee('HVAC Starter Pack')
            ->assertSee('2 assets')
            ->assertSee('Furnace tune-up reminder')
            ->assertSee('Spring AC checklist')
            ->assertDontSee('No starter pack');

        Livewire::test(PackBrowser::class)->assertOk();
    }
}
