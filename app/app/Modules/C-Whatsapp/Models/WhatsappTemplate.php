<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property string $body
 */
class WhatsappTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $guarded = [];
}
