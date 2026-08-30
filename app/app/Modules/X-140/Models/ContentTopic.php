<?php

declare(strict_types=1);

namespace App\Modules\X140\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTopic extends Model
{
    protected $table = 'content_topics';

    protected $guarded = [];

    protected $casts = [
        'similarity_score' => 'float',
        'is_published' => 'boolean',
    ];

    public function sources()
    {
        return $this->hasMany(TopicSource::class, 'topic_id');
    }
}
