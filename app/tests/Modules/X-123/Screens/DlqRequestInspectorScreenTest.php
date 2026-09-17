<?php

declare(strict_types=1);

namespace Tests\Modules\X123\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X123\Models\DeadLetter;
use App\Modules\X123\Models\EventLog;
use App\Modules\X123\Ui\DlqRequestInspector;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DlqRequestInspectorScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-123.dlq-request-inspector'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No failed deliveries.');

        Tenancy::setUser($owner->id);

        $eventLog = EventLog::create([
            'business_id' => $biz->id,
            'event_name' => 'distinctive.event.4602',
            'payload' => [],
            'status' => 'published',
        ]);

        DeadLetter::create([
            'business_id' => $biz->id,
            'event_log_id' => $eventLog->id,
            'subscription_id' => null,
            'error_message' => 'Distinctive failure 4602',
            'attempts' => 10,
        ]);

        Tenancy::forget();

        $this->get(route('x-123.dlq-request-inspector'))
            ->assertOk()
            ->assertSee('Distinctive failure 4602')
            ->assertDontSee('No failed deliveries.');

        Livewire::test(DlqRequestInspector::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-123.dlq-request-inspector.admin'))->assertOk();

        Livewire::test(DlqRequestInspector::class)->assertOk();
    }
}
