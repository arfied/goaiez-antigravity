<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The eight characters that thread a reply back to the person who wrote it.
 *
 * Decision 2097, and it is the load-bearing half of the owner's own message:
 * *"if the client does not have email configured it will smtp out an email we
 * setup for all clients"*, with **an 8-character alphanumeric tracking code
 * pairing every message to a customer and a client.** The code is what makes a
 * shared relay usable at all — a reply arriving at one mailbox for every tenant
 * carries no tenant, no customer and no message, and the address it was sent to
 * is the only thing the recipient's mail client will hand back unchanged.
 *
 * ⚠️ **NOT TENANT-OWNED, AND IT NAMES A TENANT — `impersonation_sessions`'
 * SHAPE (562) RATHER THAN `opt_outs`'.** The row carries `business_id` and
 * still cannot take `BelongsToTenant`, because **it is what establishes the
 * tenant for a request that has none**: an inbound reply is resolved by code
 * first and only then may `Tenancy::actingAs()` run. A global scope here would
 * hide every row from the one query whose job is to find out whose row it is,
 * and `Tenancy::idOrFail()` inside the trait would throw on that path before
 * the lookup ever ran. `PhoneNumber` and `GbpAccountBinding` are the two
 * nearest precedents and both say the same thing.
 *
 * ⚠️ **RLS IS ENABLE + FORCE WITH `USING (true)`**, which is `PhoneNumber`'s
 * posture and not an omission. The reader has no tenant to compare against, so
 * a policy predicated on `app.business_id` would refuse the resolution this
 * table exists for. What replaces it is written down rather than assumed: the
 * application-layer predicate is a lookup **by code**, the code is 32 bits of
 * cryptographic randomness rather than a sequence, and `MailTrackingCodes` is
 * the only writer and the only reader — held there by a chokepoint lint in
 * `tests/Feature/Architecture/MailTest.php`.
 *
 * ⚠️ **NO PERSONAL DATA. NOT THE ADDRESS, NOT THE SUBJECT, NOT THE BODY.** The
 * row is four foreign keys and a code. A platform-scoped table pairing an email
 * address with a business is a marketing list — `opt_outs` and
 * `inbound_messages` both refuse to become one and their arguments are this
 * one's. The address lives on `customers`, behind the tenant boundary, where
 * the tenant's own retention policy reaches it.
 *
 * ⚠️ **THE CODE IS NOT A SECRET AND MUST NEVER BE TREATED AS ONE.** It travels
 * in a `Reply-To` header through every mail server between here and the
 * recipient, and it comes back through as many. Guessing one buys an attacker
 * the ability to *file a reply against a contact*, which is why the routing it
 * authorises writes a status and never a consent, a suppression or a send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_tracking_codes', function (Blueprint $table): void {
            $table->id();

            /*
             * Eight characters, unambiguous alphabet, unique.
             *
             * ⚠️ UNIQUE IS THE MECHANISM RATHER THAN A TIDINESS RULE. The
             * generator retries on collision and only the index can decide the
             * race between two concurrent sends — `inbound_messages` records
             * the identical reasoning about a redelivered webhook, and
             * `StripeEvent` established the shape.
             *
             * ⚠️ CITEXT WOULD HAVE BEEN THE WRONG ANSWER even though a mail
             * server may case-fold a local part. The alphabet is uppercase-only
             * (see `MailTrackingCodes::ALPHABET`), and the resolver upper-cases
             * before it looks — so the fold is handled where it can be tested
             * rather than by an extension the schema would then depend on.
             */
            $table->string('code', 8)->unique();

            /*
             * Whose message this was.
             *
             * ⚠️ NOT NULLABLE. A code with no business is a code that resolves
             * to no tenant, which is the one state this table cannot represent
             * usefully — `PhoneNumber`'s nullable `business_id` exists because
             * the shared pool genuinely belongs to nobody, and there is no
             * equivalent here: platform mail to an account holder is not
             * tracked at all (see `PlatformMailer::send()`).
             */
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            /*
             * The message this code was minted for, when there is one.
             *
             * Nullable because not every customer-facing email is an outreach
             * message with a row of its own, and a code that could only exist
             * beside one would make the *next* customer-facing email — support
             * mail, a recovery follow-up — un-threadable.
             */
            $table->foreignId('outreach_message_id')->nullable()
                ->constrained('outreach_messages')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            /*
             * When a reply came back on this code, and nothing about the reply.
             *
             * ⚠️ **THE REPLY'S TEXT IS DELIBERATELY NOT STORED ANYWHERE**, and
             * the argument is `inbound_messages`' verbatim: free text written by
             * a member of the public, sitting in a platform-scoped table beside
             * the ids that name them, under no tenant's retention policy, is the
             * one combination this schema does not have and should not acquire.
             * What the tenant sees instead is the outreach message moving to
             * `Replied`, which `MessageLog` and `CustomerTimeline` already
             * render. The cost is real and is stated rather than hidden: the
             * owner learns that somebody replied and not what they said, until
             * a tenant-owned inbound store exists to hold it.
             */
            $table->timestamp('replied_at')->nullable();

            $table->index(['business_id', 'customer_id']);
        });

        DB::statement('ALTER TABLE mail_tracking_codes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE mail_tracking_codes FORCE ROW LEVEL SECURITY');

        /*
         * `USING (true)`, for the reason in the class docblock: the resolver
         * runs before any tenant exists. Stated as a named policy rather than
         * left off, so that `pg_policies` shows a deliberate decision instead of
         * a table somebody forgot.
         */
        DB::statement(
            'CREATE POLICY mail_tracking_codes_all ON mail_tracking_codes USING (true) WITH CHECK (true)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_tracking_codes');
    }
};
