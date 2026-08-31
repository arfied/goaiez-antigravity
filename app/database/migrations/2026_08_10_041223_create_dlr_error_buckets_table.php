<?php

declare(strict_types=1);

use App\Enums\ErrorBucket;
use App\Services\Sms\DeliveryReceipts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Infobip DLR error name → bucket — row 4 slice 6 phase 2, doc `51` §4.2, §10.
 *
 * I44: "every threshold, weight, curve, price and bucket mapping in this
 * document lives in the Defaults Registry / seeded tables; a hardcoded literal
 * fails CI." This is the bucket mapping's table — a code `match` over vendor
 * error names would be exactly the literal I44 refuses, and it would need a
 * deploy every time Infobip added one.
 *
 * NOT TENANT-OWNED, AND NO RLS — `state_messaging_rules`' shape (`CLAUDE.md`'s
 * schema row): this is a fact about a vendor's own vocabulary, not a tenant's
 * data, the same way a statute is a fact about a jurisdiction rather than about
 * whoever it binds. `App\Services\Sms\NumberHealthService` is the one reader —
 * a chokepoint lint in `MessagingTest` holds that.
 *
 * ⚠️ **SEEDED WITH EXACTLY ONE ROW, AND THE REASON IS `CLAUDE.md`'s OWN RULE:**
 * *"verify a vendor string against the raw artefact, never against memory."*
 * The only Infobip DLR error *name* this codebase has verified against a raw
 * artefact is `EC_ABSENT_SUBSCRIBER`, read into {@see DeliveryReceipts}
 * on 2026-08-09. This environment has no way to fetch Infobip's published error
 * vocabulary to verify a broader list, and inventing one from memory is exactly
 * what that rule exists to forbid — a plausible-looking error name here is the
 * worst place for one, because {@see ErrorBucket::Other} is what an unmapped
 * name resolves to and the whole table exists to let Ops correct that over
 * time, not to ship a guessed taxonomy on day one. `EC_ABSENT_SUBSCRIBER` is an
 * unreachable-handset fact about the destination, not the sending number, so it
 * seeds {@see ErrorBucket::Other} — doc 51 §4.2's own example of that bucket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dlr_error_buckets', function (Blueprint $table): void {
            $table->id();

            $table->string('error_name')->unique();

            // Cast to ErrorBucket. A string, never a database enum
            // (CLAUDE.md).
            $table->string('bucket');

            $table->timestamps();
        });

        $buckets = collect(ErrorBucket::cases())
            ->map(fn (ErrorBucket $bucket): string => "'{$bucket->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE dlr_error_buckets
                ADD CONSTRAINT dlr_error_buckets_bucket_is_known
                CHECK (bucket IN ({$buckets}))
        SQL);

        DB::table('dlr_error_buckets')->insert([
            'error_name' => 'EC_ABSENT_SUBSCRIBER',
            'bucket' => ErrorBucket::Other->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dlr_error_buckets');
    }
};
