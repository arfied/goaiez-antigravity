<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The evidence that somebody decided this reply may be published.
 *
 * ⚠️ WHY THESE COLUMNS EXIST AT ALL. `approve()` used to write
 * `status = suggested` — the identical value an untouched AI draft carries — so
 * "approved" was not representable, `PostReplyJob` could not tell a decision
 * from a draft, and the card never left the queue it had just been cleared
 * from. `ReplyStatus::Approved` is the state; these two columns are the record
 * of *who* and *when*, which is what an audit of a public post under the
 * tenant's name has to be able to answer after the fact.
 *
 * `approved_by` is an actor label rather than a `users` FK, exactly as
 * `posted_by` already is on this table: an auto-post decision has no person
 * behind it and is recorded as `system:auto_post`, which is precisely the
 * distinction a reviewer needs to see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replies', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->string('approved_by')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('replies', function (Blueprint $table): void {
            $table->dropColumn(['approved_at', 'approved_by']);
        });
    }
};
