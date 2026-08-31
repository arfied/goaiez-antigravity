<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The support desk between a tenant and GO AI EZ — T137 `SL-7`, DATA-MODEL's
 * `support_tickets` / `support_messages` (Parts 9–10).
 *
 * ⚠️ **NOT `support_settings`, AND THE TWO ARE DIFFERENT DESKS.** DATA-MODEL
 * §5.9 is the *tenant's own* desk — `conversations`, `messages`,
 * `support_settings` with its `call_routing_mode`, `ring_timeout_seconds`,
 * voicemail greetings and `forwarding_verified_at`. That is a business talking
 * to *their* customers and it belongs to T137's `SL-3` missed-call work (R7).
 * This is the tenant talking to *us*.
 *
 * ## Three tables and two boundaries
 *
 * `support_tickets` and `support_messages` are tenant-owned: the request is the
 * tenant's own record of what they asked us and what we answered, they carry
 * free text written by a person, and the tenant is their primary reader. Both
 * get `BelongsToTenant` above and `ENABLE` + `FORCE ROW LEVEL SECURITY` here.
 *
 * ⛔ **`support_queue_entries` CANNOT BE TENANT-OWNED AND THAT IS THE WHOLE
 * REASON IT EXISTS.** A staff queue is read with **no tenant established** —
 * `28` §9.1's internal roles belong to no business — and under `FORCE` RLS a
 * query with no `app.business_id` returns nothing at all, `withoutGlobalScope()`
 * included, because the policy is enforced by the database. So the queue's own
 * row sits outside the boundary, and what makes that safe is that it carries
 * **no personal data and no text**: a business id, a ticket id, a channel and
 * three timestamps. Everything a person wrote stays behind RLS, and staff reach
 * it through `Tenancy::actingAs()` one account at a time — `AccountDirectory`'s
 * pattern, and decision 800's refusal of an account list is untouched, because
 * this lists *work waiting*, never accounts to browse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The person who raised it, when a person did. Nullable because an
            // inbound email may arrive from an address matching no account user
            // — `SupportInbox` records the ticket rather than dropping it.
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // app | email | sms. A string cast to a backed enum, never a
            // database enum.
            $table->string('channel');
            $table->string('subject');
            // open | answered | resolved.
            $table->string('status')->default('open');

            $table->timestamp('last_message_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // The tenant's own list: their tickets, newest activity first.
            // `business_id` leads so the index serves the tenant-only query too.
            $table->index(['business_id', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();

            // owner | staff — who is speaking, not who they are.
            $table->string('author');
            // The staff member or the tenant user, when one is known. Kept
            // beside `author` rather than derived from it: a null id must not
            // silently turn our reply into the tenant's.
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('channel');
            $table->text('body');

            // The provider's own id for an inbound message, so a redelivered
            // webhook cannot append the same reply twice. Scoped with the
            // tenant, because two providers' ids may collide and a global
            // unique index across tenants is a cross-tenant collision (and a
            // disclosure) waiting to happen.
            $table->string('external_ref')->nullable();

            $table->timestamp('created_at');

            $table->index(['support_ticket_id', 'id']);
            $table->unique(['business_id', 'external_ref']);
        });

        Schema::create('support_queue_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();

            $table->string('channel');
            $table->timestamp('opened_at');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // One entry per ticket. The queue is a projection of the thread's
            // lifecycle, not a second thread.
            $table->unique('support_ticket_id');

            // The queue read: everything unresolved, oldest first.
            $table->index(['resolved_at', 'opened_at']);
        });

        foreach (['support_tickets', 'support_messages'] as $tenantOwned) {
            DB::statement("ALTER TABLE {$tenantOwned} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tenantOwned} FORCE ROW LEVEL SECURITY");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$tenantOwned}
                    USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)"
            );
        }

        // ⛔ `support_queue_entries` DELIBERATELY GETS NO POLICY. A tenant
        // predicate here would hide every row from the staff queue, which is the
        // only thing that reads this table — see the class docblock. It is on
        // `TenancyTest`'s un-tenanted allowlist with that argument written out,
        // and a chokepoint lint holds it to one service.
    }

    public function down(): void
    {
        Schema::dropIfExists('support_queue_entries');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
