<?php

declare(strict_types=1);

use App\Models\ShortLinkClick;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every fetch of a short link — T137 `SL-5` and §3.5's prunable event schema.
 *
 * ## The load-bearing distinction: a fetch is not a click
 *
 * ⛔ **CARRIERS, LINK CHECKERS AND MAIL SCANNERS OPEN EVERY LINK WE SEND.**
 * T137 §5 says so from the other direction — carrier link filtering is why the
 * short domain wants age — and a filter that inspects a URL does it by
 * requesting it. So the naive implementation, where every request to a token is
 * a click, reports **100% engagement on every campaign**, and it does so
 * plausibly: the numbers are large, they move when you send, and nothing about
 * them looks wrong until somebody rings a customer who never opened anything.
 *
 * ⚠️ **AND THE CRM CONSEQUENCE IS WORSE THAN THE ANALYTICS ONE.** A scanner's
 * fetch on a contact's timeline is a sentence — *"they opened your message"* —
 * about a person who did not. That is the same class of error decision 113
 * refuses for review destinations: **a click is never evidence of the thing the
 * click would suggest**, and no platform gives us a completion callback.
 *
 * So every fetch is stored and each one carries {@see ShortLinkClick::$counted}.
 * Only counted fetches reach a timeline. **Both halves are kept**: discarding the
 * scanner traffic would leave nobody able to answer why a campaign's numbers look
 * the way they do, and it is also the evidence that the filter is working.
 *
 * ⚠️ **WHAT THE FILTER CANNOT DO, SAID RATHER THAN GLOSSED** (352/397/565's
 * rule). It is a heuristic over the request's own declarations — method, a
 * prefetch header, a self-identifying user agent. A scanner that fetches with a
 * browser's user agent and a GET is indistinguishable from a person here, and
 * nothing in this schema pretends otherwise. It removes the traffic that
 * announces itself, which is most of it.
 *
 * ## No raw IP, and no assembled identifier
 *
 * `CLAUDE.md`: *never store raw IP; no fingerprinting; device signals are for
 * bot scoring and device-class bucketing only — never concatenated into a stable
 * identifier.* This table therefore holds **no address, no user-agent string and
 * no header dump**: a bucketed device class and two booleans, which is enough to
 * explain a number and not enough to follow a person between two links.
 *
 * ## §3.5: prunable from day one
 *
 * `clicked_at` is indexed and rows are immutable once written, so retention is a
 * range delete rather than a scan. Declarative partitioning is deliberately not
 * used yet, for the reasons `sending_health_windows` records at length.
 * ⛔ **The scheduled prune is not written here** — that is owed, and saying so is
 * decision 2199's rule about `SendingHealth::prune()`, which has the identical
 * gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_link_clicks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->foreignId('short_link_id')->constrained()->cascadeOnDelete();

            // Denormalised from the link so a timeline read does not have to
            // join, and so a click keeps naming the contact it was attributed to
            // even if the link is later repointed at somebody else.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('clicked_at');

            // ⚠️ **THE COLUMN THE WHOLE TABLE EXISTS FOR.** False for a fetch
            // this application believes was a machine. Only true rows are a
            // person opening a link, and only true rows reach a timeline.
            $table->boolean('counted');

            // Why it was not counted, when it was not. Null for a counted fetch.
            // A closed vocabulary cast to a PHP backed enum — never a database
            // enum.
            $table->string('discard_reason')->nullable();

            // Phone / tablet / desktop / unknown. A bucket, deliberately coarse:
            // it answers "does this campaign land on phones" and cannot be
            // combined with anything else here into an identifier.
            $table->string('device_class');

            $table->timestamps();

            // The retention delete (§3.5), and the platform-wide read.
            $table->index('clicked_at');

            // The tenant's own reporting read.
            $table->index(['business_id', 'clicked_at']);

            // The timeline's read, and the one that must stay cheap because it
            // runs on a screen a person is waiting for.
            $table->index(['business_id', 'customer_id', 'clicked_at']);
        });

        DB::statement('ALTER TABLE short_link_clicks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE short_link_clicks FORCE ROW LEVEL SECURITY');

        // ⚠️ **ONE POLICY HERE, UNLIKE `short_links`.** The click is written
        // *after* the redirector has resolved the link and established the
        // tenant, so by the time anything touches this table there is a tenant to
        // key on — and giving it `public_read` would make every tenant's click
        // stream readable by an anonymous request for no reason at all.
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON short_link_clicks
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('short_link_clicks');
    }
};
