<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Modules\X201\Domain\DisputeDefenseEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class N010Test extends TestCase
{
    public function test_n_010_no_refund_verb(): void
    {
        // N-010
        $path = app_path('Modules/X-201');
        $files = File::allFiles($path);

        $foundRefund = false;
        foreach ($files as $file) {
            $content = strtolower(file_get_contents($file->getPathname()));
            if (str_contains($content, 'refund')) {
                $foundRefund = true;
                break;
            }
        }

        $this->assertFalse($foundRefund, 'X-201 code must not contain refund verb');

        $engine = new DisputeDefenseEngine;
        $biz = TestCase::provisionTenant(['name' => 'N-010 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dispute = clone $engine->record($biz->id, 103, 10000, 'fraudulent');

        // Assert the state machine refuses any other verb (e.g., 'refunded' or 'invalid')
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid outcome'); // Or similar, expecting it to fail

        $engine->recordOutcome($biz->id, $dispute->id, 'refunded');
    }
}
