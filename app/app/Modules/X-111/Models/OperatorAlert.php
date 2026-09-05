<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use Database\Factories\OperatorAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperatorAlert extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return OperatorAlertFactory::new();
    }

    protected $table = 'operator_alerts';

    protected $guarded = [];
}
