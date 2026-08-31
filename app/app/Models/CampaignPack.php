<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Campaigns\CampaignPacks;
use App\Support\Campaigns\PackMessage;
use Database\Factories\CampaignPackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One authored campaign pack — T296 §B, CC-5 §1.
 *
 * Ours, not a tenant's: twelve cards GO AI EZ wrote for every tenant to read.
 * The creating migration carries the row-level-security argument in full, and
 * the model sits on the scope allowlist in
 * `tests/Feature/Architecture/TenancyTest.php` beside `PlanOffer` and
 * `LegalDocument` for the same reason.
 *
 * ⚠️ **A PACK IS NOT A CAMPAIGN AND NEVER BECOMES ONE.**
 * {@see CampaignPacks::activate()} *reads* a pack and *drafts* campaigns; the
 * pack row is untouched and belongs to nobody. That separation is what keeps
 * every pack send inside the existing pipeline rather than beside it — a
 * `campaigns` row is tenant-owned, RLS-protected, `CONFIRM`-gated and run by
 * `RunCampaignJob`, and nothing here is any of those things.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $one_liner
 * @property array<int, array{day_offset: int, body: string}> $messages
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CampaignPack extends Model
{
    /** @use HasFactory<CampaignPackFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The pack's messages as value objects, in the order they send.
     *
     * ⚠️ **PARSED THROUGH {@see PackMessage::fromArray()} RATHER THAN HANDED
     * BACK RAW**, so a malformed row is a named refusal here instead of an
     * undefined-index deep inside a composer that is about to text somebody.
     *
     * @return list<PackMessage>
     */
    public function orderedMessages(): array
    {
        return array_map(
            static fn (array $message): PackMessage => PackMessage::fromArray($message),
            array_values($this->messages),
        );
    }

    /**
     * Whether anything has been authored for this pack yet.
     *
     * ⛔ **ALL TWELVE ANSWER `false` TODAY, AND THAT IS A RECORDED REFUSAL
     * RATHER THAN AN UNFINISHED SEEDER** — see the creating migration and
     * decision 5253. T296 §B3 authored names and one-liners; the message bodies
     * live in the SEQ packs of record, which are not in this repository.
     */
    public function isAuthored(): bool
    {
        return $this->messages !== [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'messages' => 'array',
        ];
    }
}
