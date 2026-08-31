<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InboundMediaOutcome;
use App\Models\InboundMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **`inbound_message_id` IS DELIBERATELY ABSENT TOO, AND IT IS REQUIRED** —
 * `CampaignReplyFactory`'s reasoning verbatim. `inbound_messages` is
 * platform-scoped and written by the carrier webhook; a factory that minted one
 * here would produce a picture attached to a message nobody sent.
 *
 * ⚠️ **THE DEFAULT IS A REFUSAL, NOT A STORED ROW, AND THAT IS DELIBERATE.** A
 * `stored` row has to name a disk and a path, and a factory default pointing at
 * an object that is not there is a fixture that lies — the CHECK constraint
 * would accept it and every reader would 404. {@see self::stored()} is the state
 * that builds the whole shape, and it is the caller's job to put bytes behind
 * it.
 *
 * @extends Factory<InboundMedia>
 */
final class InboundMediaFactory extends Factory
{
    protected $model = InboundMedia::class;

    // No `@return array<string, mixed>` docblock — see CreditLedgerEntryFactory.
    public function definition(): array
    {
        return [
            'ordinal' => 0,
            'outcome' => InboundMediaOutcome::RefusedUnreachable,
            'created_at' => now(),
        ];
    }

    /**
     * A row that claims bytes, with every column the CHECK constraint requires.
     */
    public function stored(string $path = 'inbound-media/1/1/0', string $contentType = 'image/jpeg'): self
    {
        return $this->state(fn (): array => [
            'outcome' => InboundMediaOutcome::Stored,
            'content_type' => $contentType,
            'byte_size' => 1024,
            'checksum' => hash('sha256', $path),
            'storage_disk' => 's3',
            'storage_path' => $path,
        ]);
    }
}
