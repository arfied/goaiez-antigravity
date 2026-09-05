<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Models\Conversation;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Ui\CustomersList;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersListTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customers_list_renders_and_interacts(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz']);
        Tenancy::set((int) $biz->id);

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
        ]);

        LeadScore::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'lead_rating' => 95,
            'grade' => 'A',
        ]);

        $convo = Conversation::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'channel' => 'sms',
            'status' => 'open',
        ]);

        // Default state
        Livewire::test(CustomersList::class, ['businessId' => $biz->id])
            ->assertSee('Charlie Brown')
            ->assertSee('charlie@example.com')
            ->assertSee('Score: 95')
            ->call('readConversation', $convo->id)
            ->call('openPerson', $person->id)
            ->assertDispatched('openPerson', $person->id);

        // Empty state
        Person::where('business_id', $biz->id)->delete();
        Livewire::test(CustomersList::class, ['businessId' => $biz->id])
            ->assertSee('No customers yet');
    }
}
