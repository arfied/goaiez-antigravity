<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reviews.customer_id was created unconstrained in slice C because customers
 * did not exist yet; the constraint lands here, immediately after it does.
 *
 * SET NULL, not CASCADE: a review is content that outlives the customer —
 * erasure is crypto-shred, and aggregates and published content survive it.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->foreign('customer_id')
                    ->references('id')->on('customers')
                    ->nullOnDelete();

                $table->index('customer_id');
            });
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'already exists') && $e->getCode() !== '42710') {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
        });
    }
};
