<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Business extends Model
{
    protected $table = 'businesses';

    protected $guarded = [];

    public static function allocateId(): int
    {
        $row = DB::selectOne("SELECT nextval(pg_get_serial_sequence('businesses', 'id')) AS id");

        return (int) $row->id;
    }

    public static function provision(array $attributes): self
    {
        $id = self::allocateId();
        DB::statement("SET app.business_id = '{$id}'");

        $business = new self;
        $business->forceFill([...$attributes, 'id' => $id])->save();

        return $business;
    }
}
