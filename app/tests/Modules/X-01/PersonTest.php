<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Models\TakeoverLatch;
use App\Modules\X01\Ui\Person;
use App\Modules\X121\Models\Person as PersonModel;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use DatabaseTransactions;

    public function test_person_renders_timeline(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz']);
        Tenancy::set((int) $biz->id);

        $person = PersonModel::create([
            'business_id' => $biz->id,
            'first_name' => 'Snoopy',
            'last_name' => 'Dog',
            'email' => 'snoopy@example.com',
        ]);

        LeadScore::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'lead_rating' => 88,
            'grade' => 'A',
        ]);

        $convo = Conversation::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        DB::table('messages')->insert([
            'created_at' => now(),
            'business_id' => $biz->id,
            'conversation_id' => $convo->id,
            'body' => 'UniqueTimelineMessage42',
            'sender_type' => 'system',
            'direction' => 'inbound',

        ]);

        TakeoverLatch::create([
            'business_id' => $biz->id,
            'conversation_id' => $convo->id,
            'is_active' => true,
            'operator_id' => 1,
            'operator_name' => 'Operator',
        ]);

        // Default state
        Livewire::test(Person::class, ['personId' => $person->id])
            ->assertSee('Snoopy Dog')
            ->assertSee('UniqueTimelineMessage42')
            ->assertSee('Human takeover')
            ->assertSee('whatsapp')
            ->assertSee('Inbound');

        // Empty timeline state
        Message::where('conversation_id', $convo->id)->delete();
        Livewire::test(Person::class, ['personId' => $person->id])
            ->assertSee('No messages yet');

        // Not found error
        Livewire::test(Person::class, ['personId' => 999999])
            ->assertSee('Person not found');
    }

    public function test_taking_over_shows_the_human_takeover_pill(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);

        $person = PersonModel::create([
            'business_id' => $biz->id,
            'first_name' => 'Snoopy',
            'last_name' => 'Dog',
            'email' => 'snoopy@example.com',
        ]);

        $convo = Conversation::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Livewire::actingAs($owner)
            ->test(Person::class, ['personId' => $person->id])
            ->call('takeOver', $convo->id);

        $this->actingAs($owner)->get(route('x-01.person', ['person' => $person->id]))
            ->assertSee('Human takeover');

        $this->assertDatabaseHas('takeover_latches', [
            'conversation_id' => $convo->id,
            'is_active' => true,
        ]);
    }

    public function test_handing_back_clears_the_pill(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);

        $person = PersonModel::create([
            'business_id' => $biz->id,
            'first_name' => 'Snoopy',
            'last_name' => 'Dog',
            'email' => 'snoopy@example.com',
        ]);

        $convo = Conversation::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Livewire::actingAs($owner)
            ->test(Person::class, ['personId' => $person->id])
            ->call('takeOver', $convo->id)
            ->call('handBack', $convo->id);

        $this->actingAs($owner)->get(route('x-01.person', ['person' => $person->id]))
            ->assertDontSee('Human takeover');

        $this->assertDatabaseHas('takeover_latches', [
            'conversation_id' => $convo->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('takeover_latches', [
            'conversation_id' => $convo->id,
            'is_active' => false,
            'released_at' => null,
        ]);
    }

    public function test_a_staff_user_cannot_take_over(): void
    {
        $staff = User::factory()->role(UserRole::Staff)->create();

        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'owner_user_id' => $staff->id]);
        Tenancy::set((int) $biz->id);

        $person = PersonModel::create([
            'business_id' => $biz->id,
            'first_name' => 'Snoopy',
            'last_name' => 'Dog',
            'email' => 'snoopy@example.com',
        ]);

        $convo = Conversation::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Livewire::actingAs($staff)
            ->test(Person::class, ['personId' => $person->id])
            ->call('takeOver', $convo->id)
            ->assertForbidden();
    }
}
