<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Gbp\GbpConnections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Platform index from a Zernio profile id to the business whose flow made it.
 *
 * ⚠️ Not tenant-scoped — see the creating migration for the whole argument.
 * {@see GbpConnections} is the only writer and the only reader; a lint in
 * `GbpTest` holds both.
 *
 * ⛔ **THIS IS ATTRIBUTION FOR A REPORT AND IS NEVER SUFFICIENT TO BIND AN
 * ACCOUNT** (6764, 6779(a)). A profile names a *business*; a binding needs a
 * *location*, and a business with three locations connecting has three `pending`
 * rows against this one row. Anything reading here to decide where a Google
 * account belongs is reading a fact that cannot answer the question.
 *
 * ⚠️ **AND THE ROW IS NOT EVIDENCE THAT ANYTHING WAS EVER CONNECTED.** It is
 * written when an owner is *sent* to Zernio's consent screen, which is the only
 * moment the platform can still see both halves of the mapping. A business that
 * pressed Connect and closed the tab has a row here and nothing at the vendor.
 *
 * @property-read int $id
 * @property string $profile_ref
 * @property int $business_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class GbpProfileBinding extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['id'];
}
