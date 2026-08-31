<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditStatus;
use Database\Factories\PublicAuditFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One free instant audit of a public Google Business Profile (`29` §6.2).
 *
 * DELIBERATELY NOT TENANT-OWNED, and the only model in App\Models carrying
 * business information that isn't. It is written before anyone signs up, so
 * there is no tenant to scope it by — decision 177: "genuinely pre-signup state
 * lives in `public_audits`, which has no tenant by design."
 *
 * That makes it the third entry on the TenancyTest allowlist, and the
 * absence has to be argued rather than assumed, because nothing here fails
 * loudly if it is wrong: the RLS convention test only inspects models carrying
 * a tenancy trait, so it stays green whether or not this table is protected.
 * The full argument is in the creating migration; the short version is that
 * every field on this row is public information about a public listing, and the
 * controls that fit are the token, the expiry and `noindex` rather than a
 * tenant boundary.
 *
 * WHAT MUST NOT HAPPEN TO IT. When slice H pre-fills the wizard from an audit,
 * it copies name, findings and categories into `wizard_progress.data` — it does
 * not add a foreign key. This table prunes on its TTL, and a dangling reference
 * across the tenant boundary is worse than duplicated text (BUILD-PLAN §2.5.2).
 *
 * @property-read int $id
 * @property string $token
 * @property ?string $place_id
 * @property ?string $name_snapshot
 * @property list<string> $categories
 * @property AuditStatus $status
 * @property ?int $score
 * @property array<int, array<string, mixed>> $findings
 * @property array<int, array{key: string, label: string, ran: bool, reason: ?string}> $checks
 * @property ?string $ip_hash
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PublicAudit extends Model
{
    /** @use HasFactory<PublicAuditFactory> */
    use HasFactory;

    /**
     * How long a result stays reachable (decision 192, the owner's figure).
     *
     * Ninety days keeps a link alive well past the window in which a lead
     * actually decides, which is the only thing permanence bought.
     */
    public const int RETENTION_DAYS = 90;

    /**
     * The token is set by newToken() and never by mass assignment, so that a
     * request body cannot choose it.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'token'];

    /**
     * The route key is the token, so `/audit/{publicAudit}` binds on the opaque
     * identifier rather than exposing the sequential one.
     */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * A fresh, unguessable token.
     *
     * 48 characters from Str::random(), which is CSPRNG-backed — roughly 285
     * bits of entropy. Slice F asserts non-enumerability; this is the half of
     * that property which lives in the model.
     */
    public static function newToken(): string
    {
        return Str::random(48);
    }

    /**
     * When an audit created now should stop being reachable.
     */
    public static function expiryFromNow(): Carbon
    {
        return Carbon::now()->addDays(self::RETENTION_DAYS);
    }

    /**
     * Audits still within their retention window.
     *
     * Used by the read paths, so an expired row is unreachable from the moment
     * it expires rather than from whenever the pruner next runs. The pruner
     * reclaims the space; this is what makes the retention promise true in
     * between.
     *
     * @param  Builder<PublicAudit>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * Audits past their retention window, which the pruner deletes.
     *
     * @param  Builder<PublicAudit>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', Carbon::now());
    }

    public function isLive(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AuditStatus::class,
            'findings' => 'array',
            'checks' => 'array',
            'categories' => 'array',
            'score' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
