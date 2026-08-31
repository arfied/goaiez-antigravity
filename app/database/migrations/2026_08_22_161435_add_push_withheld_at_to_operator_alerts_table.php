<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bell that rang and was deliberately not pushed — 7820–7839.
 *
 * ## ⛔ Why this is a column and not an absence
 *
 * The creating migration says of `emailed_at` and `texted_at`: **"NULL is not
 * 'not sent', it is 'we do not know'"**. That was already the widest claim two
 * nulls could carry, and `OperatorAlerts` now has a third outcome that produces
 * exactly the same pair of nulls for a completely different reason: **the alert
 * fired, the channels were configured, and the push was withheld on purpose**
 * because an inbound request had already spent that kind's push budget for the
 * day.
 *
 * ⛔ **AND THERE IS A READER WHO WOULD HAVE GOT IT WRONG.**
 * `ShowOperatorAlertChannels::reportUndelivered()` — a `composer deploy` step —
 * counts rows with both timestamps null and reports them as *"reached no push
 * channel"*, whose whole purpose is to catch **a channel that does not work**.
 * Without this column a bounded pager would have read on every deployment as a
 * broken one, which is 7484's lesson in the opposite direction: there an
 * absence was mistaken for nothing happening, here an absence would be mistaken
 * for a fault.
 *
 * ⚠️ **A TIMESTAMP RATHER THAN A BOOLEAN, ON ITS TWO SIBLINGS' SHAPE.** The
 * question after an incident is *when* the pager stopped pushing, because that
 * is the moment the flood started; a flag answers a narrower question for the
 * same storage.
 *
 * ⚠️ **NO INDEX, DELIBERATELY.** The budget count reads `(kind, fired_at)` and
 * the existing `['kind', 'subject', 'fired_at']` index leads on `kind`; this
 * column is read a row at a time by a screen and a deploy step, on a table whose
 * whole population is bounded by `OperatorAlerts::RETENTION_DAYS`. An index
 * shipped for a query nobody makes is 256's shape.
 *
 * Untenanted and un-RLS'd like the rest of the table — see the creating
 * migration, and `TenancyTest`'s allowlist entry, for why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_alerts', function (Blueprint $table): void {
            $table->timestamp('push_withheld_at')->nullable()->after('texted_at');
        });
    }

    public function down(): void
    {
        Schema::table('operator_alerts', function (Blueprint $table): void {
            $table->dropColumn('push_withheld_at');
        });
    }
};
