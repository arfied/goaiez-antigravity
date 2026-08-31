<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create('operator_alerts', function (Blueprint $table): void {
            $table->id();

            // The `OperatorAlertKind` enum's value — a string cast to a PHP
            // backed enum, never a database enum (`CLAUDE.md`).
            $table->string('kind', 40);

            // What within the kind: `anthropic` for a vendor error rate,
            // `scheduler` for a heartbeat. Empty where the kind has exactly one
            // subject, so that the de-duplication key is always two columns.
            //
            // ⚠️ **IT IS PART OF THE DEDUPE KEY ON PURPOSE.** One provider being
            // down must not suppress the alert about a second one going down
            // twenty minutes later — which is precisely the shape of a real
            // incident rather than an edge case.
            $table->string('subject', 60)->default('');

            // One sentence, already rendered, safe to put in a text message.
            $table->string('summary', 300);

            // The figures behind it — counts, rates, thresholds, window length.
            // jsonb so an incident review can read what the numbers actually
            // were, for `platform_halt_incidents`' reason: by the time anybody
            // looks, the window has rolled and the sample is gone.
            $table->jsonb('context')->default('{}');

            $table->timestamp('fired_at');

            // Which bells actually rang. ⚠️ **NULL IS NOT "NOT SENT", IT IS "WE
            // DO NOT KNOW"** — delivery is queued and neither channel confirms
            // anything here. These record that the platform handed the message
            // over, which is the only claim this row is entitled to make.
            //
            // ⚠️ **THAT WAS THE HONEST SENTENCE IN THIS TREE AND IT IS NOW THE
            // CONSERVATIVE ONE — 2026-08-25 (9371). BOTH READINGS ARE KEPT.**
            // It was written when `OperatorAlerts::email()` dispatched
            // `DeliverPlatformMail` and `emailed_at` therefore meant *a row was
            // written to `jobs`*; two docblocks claimed the stronger thing and
            // **this comment was the only artefact in the tree that did not**.
            // `email()` calls `PlatformMailer::deliverNow()` now, so both
            // columns mean **the channel accepted the message** — the mailer
            // took it, or the carrier did. ⛔ **What is still true, and is why
            // the sentence above is kept rather than replaced: acceptance is
            // not arrival.** A mailbox that bounces, a number that has changed
            // hands and a message filed as spam all leave a stamp here, and
            // this platform has no feedback signal that could say otherwise
            // (open question H).
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('texted_at')->nullable();

            $table->timestamps();

            // The dedupe read is "this kind, this subject, since this moment",
            // and the Ops list reads newest first.
            $table->index(['kind', 'subject', 'fired_at']);
            $table->index('fired_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_alerts');
    }
};
