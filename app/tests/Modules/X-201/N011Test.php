<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Modules\X201\Domain\DisputeDefenseEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class N011Test extends TestCase
{
    public function test_n_011_deadline_raises_to_human(): void
    {
        // N-011
        $biz = TestCase::provisionTenant(['name' => 'N-011 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $engine = new DisputeDefenseEngine();
        $dispute = $engine->record($biz->id, 104, 30000, 'fraudulent');
        
        $now = Carbon::now();
        // Set deadline to 50 hours away
        DB::table('disputes')->where('id', $dispute->id)->update([
            'deadline_at' => $now->copy()->addHours(50)
        ]);
        
        // Travel time: deadline is now 47 hours away (< 48h)
        Carbon::setTestNow($now->copy()->addHours(3));
        
        // Command is assumed to be something like disputes:check-deadlines or x201:check-deadlines
        // If it doesn't exist, this fails, which is a correct RED test for missing behaviour.
        Artisan::call('disputes:check-deadlines');
        
        // Assert the raise audit row appears
        $audit = DB::table('dispute_audits')
            ->where('dispute_id', $dispute->id)
            ->where('action', 'raised_to_human')
            ->first();
            
        $this->assertNotNull($audit, 'Dispute should have been raised to human');
    }
}
