<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes customers.is_suppressed (row 3 slice A, decision 286).
 *
 * DATA-MODEL §5.5 lists the column and this migration deviates from it
 * deliberately. Suppression is keyed (business_id, channel, identifier), so a
 * contact can be suppressed on SMS and reachable on email. A single boolean
 * cannot represent that, and every caller that trusted it would be wrong on one
 * of the two channels.
 *
 * Callers ask ConsentService::permit() instead, which answers per channel and
 * checks suppression before consent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('is_suppressed');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('is_suppressed')->default(false);
        });
    }
};
