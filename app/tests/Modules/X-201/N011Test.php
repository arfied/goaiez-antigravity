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
        $biz1 = TestCase::provisionTenant(['name' => 'N-011 Tenant 1', 'currency' => 'USD']);
        $biz2 = TestCase::provisionTenant(['name' => 'N-011 Tenant 2', 'currency' => 'USD']);

        $engine = new DisputeDefenseEngine;

        $now = Carbon::now();

        \App\Support\Tenancy::set($biz1->id);
        $dispute1 = $engine->record($biz1->id, 104, 30000, 'fraudulent');
        DB::table('disputes')->where('id', $dispute1->id)->update([
            'deadline_at' => $now->copy()->addHours(50),
        ]);

        \App\Support\Tenancy::set($biz2->id);
        $dispute2 = $engine->record($biz2->id, 105, 40000, 'fraudulent');
        DB::table('disputes')->where('id', $dispute2->id)->update([
            'deadline_at' => $now->copy()->addHours(50),
        ]);

        \App\Support\Tenancy::forgetAll();

        Carbon::setTestNow($now->copy()->addHours(3));

        Artisan::call('disputes:check-deadlines');

        $audit1 = DB::table('dispute_audits')
            ->where('dispute_id', $dispute1->id)
            ->where('action', 'raised_to_human')
            ->first();

        $this->assertNotNull($audit1, 'Dispute 1 should have been raised to human');

        $audit2 = DB::table('dispute_audits')
            ->where('dispute_id', $dispute2->id)
            ->where('action', 'raised_to_human')
            ->first();

        $this->assertNotNull($audit2, 'Dispute 2 should have been raised to human');
    }
}
