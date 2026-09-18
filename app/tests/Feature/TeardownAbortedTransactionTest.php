<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('teardown pool release does not throw on aborted transaction', function (): void {
    $levelBefore = DB::transactionLevel();

    expect(fn () => DB::statement('SELECT * FROM table_that_does_not_exist'))->toThrow(QueryException::class);

    try {
        $thrown = null;
        try {
            $this->releaseNumbers();
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeNull();
    } finally {
        DB::rollBack();
        DB::beginTransaction();
    }
});
