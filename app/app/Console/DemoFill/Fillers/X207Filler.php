<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Models\PushDelivery;
use App\Modules\X207\Models\PushPrompt;

class X207Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-207';
    }

    public function fill(Business $business): int
    {
        if (PushPrompt::where('business_id', $business->id)->where('prompt_title', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        PushPrompt::create(['business_id' => $business->id, 'prompt_title' => self::MARKER.'Promo', 'prompt_body' => 'Check this out', 'is_active' => true]);

        $t1 = DeviceToken::create(['business_id' => $business->id, 'platform' => 'ios', 'device_token' => 'tok1', 'status' => 'active']);
        $t2 = DeviceToken::create(['business_id' => $business->id, 'platform' => 'android', 'device_token' => 'tok2', 'status' => 'retired', 'retirement_reason' => self::MARKER.'old']);

        PushDelivery::create(['business_id' => $business->id, 'device_token_id' => $t1->id, 'payload' => [], 'status' => self::MARKER.'delivered']);

        return 4;
    }

    public function purge(Business $business): int
    {
        $count = PushDelivery::where('business_id', $business->id)->where('status', 'like', self::MARKER.'%')->delete();
        $count += DeviceToken::where('business_id', $business->id)->where('retirement_reason', 'like', self::MARKER.'%')->delete();
        $count += PushPrompt::where('business_id', $business->id)->where('prompt_title', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
