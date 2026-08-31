<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve the FK slice D deferred.
 *
 * `customers.company_id` shipped as an unconstrained bigint because `companies`
 * belongs to this slice. SET NULL rather than cascade: deleting a company must
 * not delete its customers — they are people the business still knows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });
    }
};
