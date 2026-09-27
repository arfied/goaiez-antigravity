<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ZernioAccountBinding extends Model
{
    protected $table = 'zernio_account_bindings';

    protected $guarded = ['id'];
}
