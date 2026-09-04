<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $entry_type
 * @property int $amount_hundredths_cents
 * @property int $balance_after_hundredths_cents
 * @property ?string $reference_id
 * @property ?string $description
 * @property ?CarbonInterface $created_at
 */
class CreditLedgerEntry extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'credit_ledger_entries';

    protected $guarded = [];

    protected $casts = [
        'amount_hundredths_cents' => 'integer',
        'balance_after_hundredths_cents' => 'integer',
        'created_at' => 'datetime',
    ];
}
