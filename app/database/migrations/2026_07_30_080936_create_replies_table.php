<?php

declare(strict_types=1);

use App\Enums\ReplyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replies to reviews (DATA-MODEL §5.3). AI writes replies — never reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table): void {
            $table->id();

            // business_id alongside the review FK so the RLS policy has its
            // column; filled from context by BelongsToTenant.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();

            // The drafted text is the reason the row exists, so it is the one
            // non-key column here that cannot be null.
            $table->text('text');

            $table->string('status')->default(ReplyStatus::Draft->value);

            $table->timestamp('hold_until')->nullable();
            $table->timestamp('posted_at')->nullable();

            // Actor label, not a users FK — full-auto posting has no person.
            $table->string('posted_by')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        DB::statement('ALTER TABLE replies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE replies FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON replies
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};
