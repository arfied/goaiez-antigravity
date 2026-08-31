<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property ?string $phone
 * @property ?string $email
 * @property ?string $address
 * @property ?array<string, mixed> $metadata
 */
class Person extends Model
{
    protected $table = 'people';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
