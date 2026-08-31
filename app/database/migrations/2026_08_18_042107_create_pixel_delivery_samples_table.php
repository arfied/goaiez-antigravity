<?php

declare(strict_types=1);

use App\Services\Pixel\PixelDeliveryHealth;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The counter the auto-halt reads — §10's *"auto-halt on JS-error regression
 * >0.5%"*, and the thing `CLAUDE.md`'s recurring-failure list warns a threshold
 * like this one is worthless without: a writer.
 *
 * `SendingHealthWindow`'s shape, one table over: one row per (`build_token`,
 * minute bucket), two counters, incremented with `INSERT … ON CONFLICT DO
 * UPDATE SET col = col + 1` so concurrent collector requests never lose an
 * increment to a read-modify-write race. {@see PixelDeliveryHealth}
 * is the only reader and writer.
 *
 * ⚠️ **`pageviews` AND `js_errors` SHARE ONE DENOMINATOR RULE**
 * (`SendingHealth`'s "the denominator must have the same membership as the
 * numerator", decision 3032): both are counted from the same archived batches,
 * on the same gate — a batch the HIPAA gate refuses contributes to neither, so
 * the rate this table can answer is never distorted by traffic that was never
 * archived.
 *
 * ## No `business_id`, and platform-scoped for the same reason `pixel_bundle_versions` is
 *
 * A canary is a property of the *bundle*, served identically to every tenant's
 * page at once — there is no tenant to scope this by, and a per-tenant version
 * of it would ask which tenant's error rate halts a rollout serving all of them.
 * Named exception in `TenancyTest`'s `$exempt` list, same argument.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_delivery_samples', function (Blueprint $table): void {
            $table->id();

            $table->string('build_token', 32);

            // UTC, truncated to the minute — `SendingHealth::bucket()`'s rule,
            // one grain finer because a canary window is 60 minutes rather than
            // the messaging health table's rolling 24 hours.
            $table->timestamp('bucket');

            $table->unsignedBigInteger('pageviews')->default(0);
            $table->unsignedBigInteger('js_errors')->default(0);

            $table->timestamps();

            $table->unique(['build_token', 'bucket']);
        });

        // No RLS: platform-scoped, argued in the class docblock and in
        // TenancyTest's $exempt list.
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_delivery_samples');
    }
};
