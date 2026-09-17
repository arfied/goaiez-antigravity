<?php

declare(strict_types=1);

namespace Tests\Modules\X01\Screens;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Modules\X01\Ui\History;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-01.history'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing has happened yet.');

        Tenancy::setUser($owner->id);
        $convo = Conversation::create([
            'business_id' => $biz->id,
            'channel' => 'sms',
        ]);
        Message::create([
            'business_id' => $biz->id,
            'conversation_id' => $convo->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'body' => 'Distinctive activity 4632',
        ]);
        Tenancy::forget();

        $this->get(route('x-01.history'))
            ->assertOk()
            ->assertSee('Distinctive activity 4632')
            ->assertDontSee('Nothing has happened yet.');

        Livewire::test(History::class)->assertOk();
    }
}
