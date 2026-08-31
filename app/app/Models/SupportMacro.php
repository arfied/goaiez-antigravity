<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Support\SupportMacros;
use Database\Factories\SupportMacroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One paste from the support macro library — T308 §A3, CC-5 §4.
 *
 * Ours in the plainest sense on the allowlist: **this is what GO AI EZ staff say
 * to a tenant.** No tenant reads it and none writes it. The creating migration
 * carries the row-level-security argument; the model sits on the scope allowlist
 * in `tests/Feature/Architecture/TenancyTest.php` beside `CampaignPack` and
 * `LegalDocument`.
 *
 * ⚠️ **THE BODY IS A DRAFT AND NOT A MESSAGE.** {@see SupportMacros::rendered()}
 * binds the one canon slot and hands the rest to an agent to fill; nothing here
 * is sendable until {@see SupportMacros::assertEverySlotResolved()} has agreed
 * that nobody left a `{slot}` in it.
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $body
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SupportMacro extends Model
{
    /** @use HasFactory<SupportMacroFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
