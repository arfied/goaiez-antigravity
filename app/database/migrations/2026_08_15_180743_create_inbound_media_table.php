<?php

declare(strict_types=1);

use App\Enums\InboundMediaOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a customer texted us a picture of — T176 P10, skill 12 (photo intake).
 *
 * ⛔ **IT IS TENANT-OWNED AND `inbound_messages` IS NOT, WHICH IS THE WHOLE
 * REASON IT IS A SECOND TABLE** — `campaign_replies`' argument verbatim, and it
 * is stronger here. That table is deliberately platform-scoped, holds no tenant,
 * and its own docblock is explicit that it carries *"no message body and no
 * customer's phone number"*. A photograph a member of the public took is the
 * most content-bearing thing this system receives; it cannot sit on a
 * platform-wide table under nobody's retention policy. So this row carries
 * `business_id`, RLS is enabled and forced, and it is the tenant half of a fact
 * whose other half stays platform-side.
 *
 * ⛔ **THERE IS NO URL COLUMN, AND ADDING ONE IS THE MISTAKE THIS DOCBLOCK
 * EXISTS TO STOP.** The media address arrives inside a webhook body — untrusted
 * until the signature verifies, and untrusted content afterwards. It is used
 * exactly once, by `App\Services\Sms\InboundMediaFetcher`, under an allowlist, a
 * private-address rejection, a size ceiling and a content-type check. A durable
 * column holding it would be an attacker-chosen address sitting in a row that a
 * later screen, export or repair script could re-fetch, at which point every one
 * of those guards is somewhere else.
 *
 * ⛔ **AND THERE IS NO FILENAME COLUMN EITHER.** A carrier-supplied filename is
 * free text chosen by the sender; it would end up rendered on a staff screen and
 * used to build a download name, which is two injection surfaces bought for a
 * string nobody needs. The `content_type` is what a browser is told.
 *
 * ## Every refusal is a row
 *
 * ⚠️ **A REFUSAL THAT WRITES NOTHING READS EXACTLY LIKE AN MMS WITH NO MEDIA IN
 * IT**, and the two need opposite responses. {@see InboundMediaOutcome} carries
 * five refusal cases and each one lands here with `storage_path` null, so the
 * question *"did a customer send us something we chose not to keep"* has an
 * answer. ⛔ **The most important of them is `refused_health_tenant`** — a PHI
 * tenant's inbound picture is never fetched at all (4166), and the row is the
 * only record that the decision was taken.
 *
 * ## Idempotency
 *
 * ⚠️ **THE UNIQUE KEY IS `(business_id, inbound_message_id, ordinal)` AND IT IS
 * THE SECOND LAYER, NOT THE FIRST.** A redelivered webhook never reaches this
 * table: `inbound_messages.provider_message_id` is unique and `InboundMessages`
 * returns early when the database refuses the second insert. This is for the
 * *job* — a queue redelivery, a retry after a lost acknowledgement, or two
 * workers on one message — and it is what makes the capture safe to retry.
 *
 * ⚠️ **SCOPED BY `business_id` RATHER THAN GLOBAL**, on `campaign_replies`'
 * reasoning: a bare unique would refuse a second tenant's insert with a
 * constraint violation naming a row RLS forbids them from seeing, which is
 * existence leaking through an error message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_media', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ⚠️ **`cascadeOnDelete` CAN NEVER FIRE AND IS STILL THE RIGHT
            // EDGE**, exactly as on `campaign_replies`. `InboundMessage` refuses
            // `deleting()` outright so that a redelivery stays a no-op, so
            // nothing can delete the parent. Declaring the cascade says what
            // would be true if that ever changed.
            $table->foreignId('inbound_message_id')->constrained()->cascadeOnDelete();

            // Which part of the multipart message this is, zero-based and in
            // the order the payload listed them. It is half of the idempotency
            // key and half of the storage path, which is why it is a real
            // column rather than an array index nobody wrote down.
            $table->unsignedSmallInteger('ordinal');

            // A string cast to App\Enums\InboundMediaOutcome — never a Postgres
            // enum type (CLAUDE.md; an ConventionsTest lint fails the build on
            // `->enum(`). Closed by the CHECK below, because this column decides
            // whether the storage columns may be null.
            $table->string('outcome');

            // ⚠️ **THE TYPE WE OBSERVED, NOT THE TYPE THE PAYLOAD CLAIMED.** The
            // fetcher compares the response header against the file's own magic
            // bytes and refuses a disagreement, so what lands here is a value
            // both agreed on. Null on every refusal.
            $table->string('content_type')->nullable();

            $table->unsignedInteger('byte_size')->nullable();

            // ⚠️ **SHA-256 OF THE BYTES, PLAIN AND UNKEYED, AND IT IS NOT AN
            // IDENTIFIER.** It answers "is this the same picture as that one"
            // for a support question and "did the object change under us" for a
            // restore. It is deliberately **not** used for de-duplication across
            // tenants: two businesses that receive the same photograph each keep
            // their own copy, because a shared object is a cross-tenant read
            // waiting for somebody to optimise for it.
            $table->char('checksum', 64)->nullable();

            // Which disk holds it. Recorded rather than assumed, so that a move
            // between object stores can be told what it has already moved.
            $table->string('storage_disk')->nullable();

            $table->string('storage_path')->nullable();

            $table->timestamp('created_at')->nullable();

            // See the class docblock: the second layer, for the job.
            $table->unique(['business_id', 'inbound_message_id', 'ordinal']);

            // The contact timeline's read. RLS predicates `business_id` on every
            // query and Postgres does not index a foreign key automatically —
            // the MySQL habit that does not transfer.
            $table->index(['business_id', 'created_at']);
        });

        $outcomes = collect(InboundMediaOutcome::cases())
            ->map(fn (InboundMediaOutcome $outcome): string => "'{$outcome->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE inbound_media
                ADD CONSTRAINT inbound_media_outcome_is_known
                CHECK (outcome IN ({$outcomes}))
        SQL);

        // ⛔ **STORED MEANS BYTES, AND A REFUSAL MEANS NONE — AT THE DATABASE.**
        // The application already refuses both halves, and this is decision
        // 216's second layer: it catches the repair script that reached neither
        // the service nor the model. A `stored` row with a null path is a
        // promise of a picture that is not there, and a refusal carrying a path
        // is a claim we kept something we said we would not.
        DB::statement(<<<'SQL'
            ALTER TABLE inbound_media
                ADD CONSTRAINT inbound_media_stored_rows_carry_bytes
                CHECK (
                    (outcome =  'stored' AND storage_path IS NOT NULL AND storage_disk IS NOT NULL
                                         AND content_type IS NOT NULL AND byte_size   IS NOT NULL
                                         AND checksum     IS NOT NULL AND byte_size    > 0)
                 OR (outcome <> 'stored' AND storage_path IS     NULL AND storage_disk IS     NULL
                                         AND content_type IS     NULL AND byte_size    IS     NULL
                                         AND checksum     IS     NULL)
                )
        SQL);

        DB::statement('ALTER TABLE inbound_media ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE inbound_media FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON inbound_media
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_media');
    }
};
