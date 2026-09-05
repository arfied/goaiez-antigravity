<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Models\TakeoverLatch;
use App\Modules\X01\Ui\Person;
use App\Modules\X121\Models\Conversation;
use App\Modules\X121\Models\Message;
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
}
