<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\WizardStep;
use Database\Factories\WizardProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Setup-wizard state, one row per user per business (DATA-MODEL §5.12).
 *
 * Tenant-owned. `29` §6.2 orders signup as `POST /register` -> tenant
 * auto-provision -> wizard, so the business exists before this row does. Its
 * `data` bag carries the audit pre-fill — business name, findings, categories —
 * which is tenant-identifying and belongs inside the boundary.
 *
 * See docs/DECISIONS.md 177.
 *
 * @property-read int $id
 * @property WizardStep $current_step
 * @property bool $completed
 * @property ?int $setup_score
 * @property ?array<string, mixed> $data
 */
final class WizardProgress extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<WizardProgressFactory> */
    use HasFactory;

    /**
     * Not derivable from the class name ("progresses" is nobody's plural).
     */
    protected $table = 'wizard_progress';

    /**
     * `current_step` and `completed` are guarded on purpose, following the
     * reasoning `Plugin::$guarded` states for its own three: `SetupFlow` is
     * the one place completion is decided — `complete()` refuses to write
     * `completed = true` until `mayComplete()` holds, throwing otherwise — and
     * a later caller reaching either column directly
     * (`$progress->update(['completed' => true])`) would leave a wizard
     * marked complete with `ReviewRules` unanswered — so nobody was ever shown
     * what their seeded invite threshold is about to start doing, which is
     * exactly the state that `RuntimeException` exists to prevent.
     * `SetupFlow` itself only ever writes them via `forceFill()`, so it is
     * unaffected; `TenantProvisioner::provision()` switched to the same
     * pattern for the same reason.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'current_step', 'completed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_step' => WizardStep::class,
            'completed' => 'boolean',
            'setup_score' => 'integer',
            'data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
