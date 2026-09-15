<?php

namespace Tests\Feature\Sms;

use App\Models\PhoneNumber;
use App\Modules\CSms\Events\MessageReceived;
use App\Modules\X121\Models\Person;
use App\Services\Sms\InboundMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InboundMessagesDispatchesMessageReceivedTest extends TestCase
{
    public function test_inbound_messages_dispatches_event(): void
    {
        $biz = self::provisionTenant(['name' => 'Dispatch Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $toNumber = PhoneNumber::where('business_id', $biz->id)->first()->e164;

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Ana', 'phone' => '+15125550421']);

        Event::fake([MessageReceived::class]);

        $service = app(InboundMessages::class);
        $service->handle('msg_distinctive_4501', '+15125550421', '5', now()->toIso8601String(), $toNumber);

        Event::assertDispatched(MessageReceived::class, function ($e) {
            return $e->body === '5' && $e->fromPhone === '+15125550421';
        });
    }
}
