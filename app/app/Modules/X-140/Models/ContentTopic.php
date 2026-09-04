<?php

declare(strict_types=1);

namespace App\Modules\X140\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentTopic extends Model
{
    protected $table = 'content_topics';

    protected $guarded = [];

    protected $casts = [
        'similarity_rate' => 'float',
        'is_published' => 'boolean',
    ];

    /**
     * @return HasMany<TopicSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(TopicSource::class, 'topic_id');
    }
}
