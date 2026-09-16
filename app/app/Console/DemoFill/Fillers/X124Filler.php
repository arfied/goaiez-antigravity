<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X124\Models\AssistantRecommendation;
use App\Modules\X124\Models\AssistantUnsupported;
use Illuminate\Support\Facades\DB;

class X124Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-124';
    }

    public function fill(Business $business): int
    {
        if (AssistantUnsupported::where('business_id', $business->id)->where('utterance', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $session_id = DB::table('assistant_sessions')->insertGetId([
            'business_id' => $business->id,
            'user_id' => null,
            'session_token' => self::MARKER.'_token_'.$business->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AssistantUnsupported::create(['business_id' => $business->id, 'session_id' => $session_id, 'utterance' => self::MARKER.'can you walk my dog', 'response_returned' => 'I cannot do that.']);
        AssistantUnsupported::create(['business_id' => $business->id, 'session_id' => $session_id, 'utterance' => self::MARKER.'brew coffee', 'response_returned' => 'I cannot brew coffee.']);

        AssistantRecommendation::create(['business_id' => $business->id, 'title' => self::MARKER.'Enable feature', 'action_key' => 'enable_feature', 'status' => 'active', 'session_id' => $session_id]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $count = AssistantUnsupported::where('business_id', $business->id)->where('utterance', 'like', self::MARKER.'%')->delete();
        $count += AssistantRecommendation::where('business_id', $business->id)->where('title', 'like', self::MARKER.'%')->delete();
        DB::table('assistant_sessions')->where('business_id', $business->id)->where('session_token', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
