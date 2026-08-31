<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OwnerNotifyNumberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Whose mobile this is, for the owner channel (10540) — one row per business.
 *
 * ⛔ **DELIBERATELY NOT TENANT-SCOPED.** See the creating migration in full;
 * the short version is that `App\Services\Sms\InboundMessages` has to be able
 * to ask "is this sender a known owner" with no tenant established at all, and
 * a global scope would answer that query with zero rows every time.
 *
 * ⚠️ **ONE WRITER.** `App\Services\Consent\OwnerConsentService` is the only
 * file in `app/` permitted to touch this model, held there by an
 * `ArchitectureTest` lint in `tests/Feature/Architecture/OwnerChannelTest.php`
 * — `PhoneNumber`'s and `NumberStateChange`'s precedent, applied to a table
 * that carries a person's own mobile number rather than one of ours.
 *
 * @property-read int $id
 * @property int $business_id
 * @property string $e164
 * @property ?Carbon $stopped_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
final class OwnerNotifyNumber extends Model
{
    /** @use HasFactory<OwnerNotifyNumberFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stopped_at' => 'datetime',
        ];
    }
}
