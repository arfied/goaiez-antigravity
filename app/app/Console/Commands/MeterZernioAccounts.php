<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gbp\ZernioSpend;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Record today's connected Zernio accounts, and report the month's bill.
 *
 * ⚠️ **THIS IS THE WRITER FOR `zernio_account_days`, AND IT IS THE HALF THAT
 * MAKES THE CEILING MEAN ANYTHING** (272; `sending_health_windows`, 2496–2499).
 * `ZernioSpend::allowsNewAccount()` does not read this table at all — it reads
 * the live binding count — so the suite would stay green with nothing writing
 * here and the *accrual* would read zero forever while the brake still worked.
 * That is the exact shape this codebase keeps shipping: a threshold that fires
 * beside a counter that does not move.
 *
 * ⚠️ **IT DOES NOT ENUMERATE TENANTS AND MUST NOT START.** `gbp_account_bindings`
 * is the platform index built precisely so a sweep with no tenant can read it
 * (`gbp_connections` is RLS-`FORCE`d and returns zero rows from out here). None
 * of `RefreshOauthTokens`' owner-walk is needed or wanted; adding it would give
 * this command a tenant privilege it has no use for.
 *
 * Idempotent: the unique index on (`account_ref`, `on_day`) means a second run
 * on the same day writes nothing, so a retried cron cannot overstate the bill.
 */
#[Signature('zernio:meter')]
#[Description('Record today\'s connected Zernio accounts and report the month to date')]
final class MeterZernioAccounts extends Command
{
    public function handle(ZernioSpend $spend): int
    {
        $written = $spend->recordAccountDaysToday();
        $connected = $spend->connectedAccounts();

        $this->info('Recorded '.$written.' account-day(s) for '.$connected.' connected account(s).');

        $accrued = $spend->accruedCentsThisMonth();
        $steady = $spend->steadyStateMonthCents($connected);
        $ceiling = $spend->monthlyCeilingCents();
        $used = $spend->ceilingUsedPercent();

        $this->line(sprintf(
            'Month to date: $%s accrued (%.2f billable units). At this connection count a full month is $%s, against a $%s ceiling — %d%% used.',
            number_format($accrued / 100, 2),
            $spend->billableUnits(),
            number_format($steady / 100, 2),
            number_format($ceiling / 100, 2),
            $used,
        ));

        // ⚠️ Printed rather than alerted, and that is a stated limit rather than
        // an oversight. An alert that fires every day once a threshold is
        // crossed is noise nobody reads by the third morning, and one that fires
        // only on the crossing needs a stored "have we said this already" —
        // which is a state machine, and `sending_health_windows` is what a
        // half-built one looks like. The line below reaches the cron log, which
        // is where the deployment guide already sends somebody.
        if ($used >= 80) {
            $this->warn(
                'Connected accounts are at '.$used.'% of the Zernio monthly ceiling. '
                .'Past it, new Google connections route to the Copy + Open Google handoff instead. '
                .'Raise '.ZernioSpend::CEILING_KEY.' in platform settings, or leave it and accept the handoff path.'
            );
        }

        return self::SUCCESS;
    }
}
