<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Every bell this platform has rung at its operator — P23.
 *
 * ## It is the de-duplication, and that is why it is a table
 *
 * ⚠️ **AN ALERT THAT FIRES EVERY FIVE MINUTES IS AN ALERT SOMEBODY MUTES, AND A
 * MUTED ALERT IS WORSE THAN NONE** (511's failure, applied to a pager). The
 * watch runs on a schedule and a broken thing stays broken between runs, so
 * without a record of what has already been raised the first outage sends the
 * operator two hundred texts and the second outage sends them none, because by
 * then the number is silenced.
 *
 * ⚠️ **A CACHE ENTRY WOULD ALSO DE-DUPLICATE AND IS DELIBERATELY NOT WHAT THIS
 * IS.** `MailQuota` uses one and says why: losing it costs one extra log line.
 * Here the row is *also* the record — "what fired last night, and what did it
 * say" is the first question after an incident, and a cache flush during a
 * deploy must not be able to answer it with silence.
 *
 * ## No tenant, and therefore no RLS
 *
 * A failed-job spike, a dead scheduler and a model provider returning 500s are
 * facts about the platform. `platform_halt_incidents` (2119) settled the shape
 * for exactly this case and the argument is not repeated here: a nullable
 * `business_id` forces a policy admitting NULL, and a policy admitting NULL
 * admits every row.
 *
 * ⛔ **AND NOTHING PERSONAL MAY BE WRITTEN HERE.** `summary` and `context` are
 * rendered into an email and a text message that leave the building, and this
 * row is read by anyone with Ops access. The writers pass counts, rates,
 * thresholds and names of ours. **Never a customer, never a phone number, never
 * a vendor's raw error string** — `VendorLog`'s rule, for its reason: a
 * connection exception's message contains the URI, and for some vendors the URI
 * contains the credential.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema::create removed for operator_alerts to fix duplicates
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_alerts');
    }
};
