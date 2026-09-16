<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Models\ReplyCode;
use App\Modules\X153\Ui\AlertReplyBy;
use Livewire\Livewire;
use Tests\TestCase;

class AlertReplyByScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-153.alert-reply-by'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No active reply codes pending.');

        Livewire::test(AlertReplyBy::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-153.alert-reply-by.admin'))->assertOk();

        Livewire::test(AlertReplyBy::class)->assertOk();
    }

    /** Prove it shows the tenant's reply codes on real route */
    public function test_shows_tenant_reply_codes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $alert = Alert::create([
            'business_id' => $biz->id,
            'alert_class' => 'urgent',
            'title' => 'Test',
            'body' => 'Body text',
            'status' => 'pending',
        ]);

        ReplyCode::create([
            'business_id' => $biz->id,
            'alert_id' => $alert->id,
            'code' => '987',
            'is_live' => true,
        ]);

        $this->actingAs($owner);

        $this->get(route('x-153.alert-reply-by'))
            ->assertOk()
            ->assertSee('Code #987');

        Livewire::test(AlertReplyBy::class)
            ->assertOk()
            ->assertSee('Code #987');
    }
}
