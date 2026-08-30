<?php

declare(strict_types=1);

namespace App\Modules\X144\Models;

use Illuminate\Database\Eloquent\Model;

class VisibilityQuery extends Model
{
    protected $table = 'visibility_queries';

    protected $guarded = [];

    public function answers()
    {
        return $this->hasMany(VisibilityAnswer::class, 'query_id');
    }
}
