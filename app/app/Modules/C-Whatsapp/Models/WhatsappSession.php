<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $phone
 * @property ?CarbonInterface $last_inbound_at
 * @property ?CarbonInterface $session_window_expires_at
 * @property bool $is_window_open
 */
class WhatsappSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $guarded = [];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'session_window_expires_at' => 'datetime',
        'is_window_open' => 'boolean',
    ];
}
