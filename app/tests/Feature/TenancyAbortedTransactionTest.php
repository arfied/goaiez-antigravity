<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('tenancy actingAs does not roll back the transaction on 25P02', function (): void {
    $business = Business::factory()->create();

    $levelBefore = DB::transactionLevel();

    expect(fn () => DB::statement('SELECT * FROM table_that_does_not_exist'))->toThrow(QueryException::class);

    $thrown = null;
    try {
        Tenancy::actingAs($business->id, fn () => null);
    } catch (\Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeNull();

    $levelAfter = DB::transactionLevel();
    expect($levelAfter)->toBe($levelBefore, 'transactionLevel mismatch');

    // Clear the aborted state for Pest's teardown hooks
    DB::rollBack();
    DB::beginTransaction();
});
