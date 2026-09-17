<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Models\Conversation;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\DB;

class X01Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-01';
    }

    public function fill(Business $business): int
    {
        if (Person::where('business_id', $business->id)
            ->where('first_name', 'like', self::MARKER.'%')
            ->where('last_name', 'like', '%Inbox%')
            ->exists()) {
            return 0;
        }

        $person = Person::create([
            'business_id' => $business->id,
            'first_name' => self::MARKER.'Marcus',
            'last_name' => 'Inbox Customer',
            'email' => 'marcus@partner-demo.example',
            'phone' => '+15125554629',
        ]);

        LeadScore::create([
            'business_id' => $business->id,
            'person_id' => $person->id,
            'lead_rating' => 88,
            'grade' => 'A',
        ]);

        \App\Modules\X01\Models\ContactTag::create([
            'business_id' => $business->id,
            'contact_id' => $person->id,
            'tag' => 'demo·repeat customer',
        ]);

        $conversation = Conversation::create([
            'business_id' => $business->id,
            'channel' => 'sms',
            'subject' => self::MARKER.'Water heater quote',
            'status' => 'open',
            'person_id' => $person->id,
        ]);

        DB::table('messages')->insert([
            [
                'business_id' => $business->id,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'body' => self::MARKER.'Is the water heater quote still good?',
                'created_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'sender_type' => 'person',
                'body' => self::MARKER.'Yes — it holds through the end of the month.',
                'created_at' => now(),
            ],
        ]);

        return 6;
    }

    public function purge(Business $business): int
    {
        $count = 0;

        $persons = Person::where('business_id', $business->id)
            ->where('first_name', 'like', self::MARKER.'%')
            ->where('last_name', 'like', '%Inbox%')
            ->get();

        if ($persons->isEmpty()) {
            return 0;
        }

        foreach ($persons as $person) {
            $conversations = Conversation::where('person_id', $person->id)->get();
            foreach ($conversations as $conversation) {
                $count += DB::table('messages')->where('conversation_id', $conversation->id)->delete();
                $conversation->delete();
                $count++;
            }

            $count += LeadScore::where('person_id', $person->id)->delete();
            $count += \App\Modules\X01\Models\ContactTag::where('contact_id', $person->id)->where('tag', 'demo·repeat customer')->delete();
            $person->delete();
            $count++;
        }

        return $count;
    }
}
