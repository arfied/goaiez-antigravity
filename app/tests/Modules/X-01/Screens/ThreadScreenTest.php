<?php

declare(strict_types=1);

namespace Tests\Modules\X01\Screens;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;
use App\Modules\X01\Ui\Thread;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-01.thread'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No conversations recorded.');

        Tenancy::setUser($owner->id);
        $conversation = Conversation::create([
            'channel' => 'sms',
            'subject' => 'Distinctive thread 4630',
            'status' => 'open',
        ]);
        DB::table('messages')->insert([
            'business_id' => $biz->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'body' => 'Distinctive question 4630',
            'created_at' => now(),
        ]);
        Tenancy::forget();

        $this->get(route('x-01.thread'))
            ->assertOk()
            ->assertSee('Distinctive thread 4630');

        Livewire::test(Thread::class)->assertOk();
    }
}
