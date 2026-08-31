<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive — the third contact state (`44` §8, D-205; decision 1327).
 *
 * A timestamp rather than a boolean, because *when* the owner hid a contact is
 * the first thing anybody asks when a send was refused — and because a boolean
 * that can disagree with nothing is the shape `customers.is_suppressed` should
 * have had and did not (286).
 *
 * ⚠️ **ARCHIVE IS NOT SUPPRESSION AND NOT DELETION.** It is the owner's tidying:
 * hidden from the default list and pickers, excluded from sends by
 * `ConsentService::decide()` asking this column (1327 — the list query is the
 * tempting enforcement point and the wrong one, because a send path hydrating a
 * customer id from a queue payload never runs the list query). The timeline
 * stays intact and restore is one tap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
