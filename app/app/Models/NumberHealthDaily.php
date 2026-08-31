<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Sms\NumberHealthService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One number's one day — doc `51` §4.4's "the truth" behind
 * `phone_numbers.health_score`. See the creating migration for the RLS
 * argument and for why `phone_numbers.health_score` still has no reader.
 *
 * NOT TENANT-OWNED, DESPITE CARRYING RLS — see the creating migration; the
 * shape is `phone_numbers`', not `number_state_changes`'. Written only by
 * {@see NumberHealthService}; a chokepoint lint in `MessagingTest` confines
 * this model to that one file, which is I45's "one scorer" made mechanical.
 *
 * @property-read int $id
 * @property int $number_id
 * @property Carbon $date
 * @property int $sends
 * @property int $accepted
 * @property int $delivered
 * @property int $failed_filtered
 * @property int $failed_other
 * @property int $stops
 * @property int $replies
 * @property int $score
 * @property ?array<string, int> $by_class
 */
final class NumberHealthDaily extends Model
{
    /**
     * ⚠️ **EXPLICIT, BECAUSE ELOQUENT'S PLURALISER GETS `Daily` WRONG.** The
     * convention-derived name is `number_health_dailies` — "Daily" pluralises
     * as though it were an ordinary "y"-ending noun — and the migration this
     * model reads is named `number_health_daily`, singular, on doc 51 §10's
     * own naming. Left to the convention, every query against this model
     * fails with "relation … does not exist", which is a schema error and not
     * a clue about which of the two names is wrong.
     */
    protected $table = 'number_health_daily';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sends' => 'integer',
            'accepted' => 'integer',
            'delivered' => 'integer',
            'failed_filtered' => 'integer',
            'failed_other' => 'integer',
            'stops' => 'integer',
            'replies' => 'integer',
            'score' => 'integer',
            'by_class' => 'array',
        ];
    }
}
