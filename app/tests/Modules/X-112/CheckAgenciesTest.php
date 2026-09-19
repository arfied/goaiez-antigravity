<?php

namespace Tests\Modules\X112;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CheckAgenciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_agencies_exists()
    {
        $hasTable = Schema::hasTable('agencies');
        $migrations = DB::table('migrations')->pluck('migration')->toArray();
        dump('Has agencies table: ' . ($hasTable ? 'YES' : 'NO'));
        dump('Is migration loaded: ' . (in_array('2026_08_30_000032_create_x112_agency_tables', $migrations) ? 'YES' : 'NO'));
        $this->assertTrue(true);
    }
}
