<?php

namespace Tests\Modules\X192;

use App\Models\User;
use App\Modules\X192\Actions\MembershipBuildAction;
use App\Modules\X192\Ui\MembershipsList;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class X192Test extends TestCase
{
    public function test_guest_redirect()
    {
        $this->get('/memberships')->assertRedirect('/login');
    }

    /**
     * @see [G8-08] - Memberships screen correctly fetches tenant data.
     * @see [G8-28] - Memberships component successfully lists directory memberships.
     */
    public function test_memberships_screen()
    {
        $user = User::factory()->create();
        $business = static::provisionTenant(['owner_user_id' => $user->id]);

        DB::table('directory_memberships')->insert([
            ['business_id' => $business->id, 'directory_name' => 'Indexed Dir', 'directory_url' => 'http://example.com/1', 'is_noindex' => false, 'directory_index' => 10, 'approved_by_action_id' => 1, 'is_purchased' => true],
            ['business_id' => $business->id, 'directory_name' => 'NoIndex Dir', 'directory_url' => 'http://example.com/2', 'is_noindex' => true, 'directory_index' => 20, 'approved_by_action_id' => 1, 'is_purchased' => true],
        ]);

        $response = $this->actingAs($user)->get('/memberships');
        $response->assertOk();
        $response->assertSeeLivewire(MembershipsList::class);
        $response->assertSee("Google can't see this", false);

        Livewire::test(MembershipsList::class)
            ->assertSeeInOrder(['Indexed Dir', 'NoIndex Dir']);
    }

    public function test_no_membership_purchased_without_approval_action_row()
    {
        $user = User::factory()->create();
        $business = static::provisionTenant(['owner_user_id' => $user->id]);

        $id = DB::table('directory_memberships')->insertGetId([
            'business_id' => $business->id,
            'directory_name' => 'Should Fail',
            'directory_url' => 'http://example.com',
            'is_noindex' => false,
            'directory_index' => 50,
            'is_purchased' => false,
        ]);

        $action = new MembershipBuildAction;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase rejected: paid directory membership requires an explicit approval action row (TEST ANCHOR)');

        $action->buildProfile($business->id, $id, true, null);
    }
}
