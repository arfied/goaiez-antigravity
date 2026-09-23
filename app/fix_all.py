import sys
import re

def rep(file_path, search, replace):
    with open(file_path, 'r') as f:
        content = f.read()
    if search not in content and not hasattr(search, 'pattern'):
        print(f"Not found in {file_path}: {search}")
    
    if hasattr(search, 'pattern'):
        content = search.sub(replace, content)
    else:
        content = content.replace(search, replace)
        
    with open(file_path, 'w') as f:
        f.write(content)


# InvoiceEngine fix
p = 'app/Modules/X-199/Domain/InvoiceEngine.php'
rep(p, 'public function __construct(private GatewayEngine $gateway, private DefaultsRegistry $registry)\n    {\n    }', 'public function __construct(private DefaultsRegistry $registry)\n    {\n    }')
rep(p, "'credit_limit_cents' => 500000,", "'credit_limit_cents' => $this->defaultCreditLimitCents(),")

# InvoiceDraftAction
p = 'app/Modules/X-199/Actions/InvoiceDraftAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class InvoiceDraftAction\n{', '''final class InvoiceDraftAction
{
    public const DEFAULT_DUE_DAYS = 30;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function defaultDueDays(): int
    {
        return $this->registry->int('invoices.default_due_days', self::DEFAULT_DUE_DAYS);
    }
''')
rep(p, 'public function handle(int $businessId, int $customerId, array $lines, int $dueDays = 30): Invoice', 'public function handle(int $businessId, int $customerId, array $lines, ?int $dueDays = null): Invoice')
rep(p, 'use ($businessId, $customerId, $lines, $dueDays)', 'use ($businessId, $customerId, $lines, $dueDays)')
rep(p, '$totalCents = 0;', '$dueDays ??= $this->defaultDueDays();\n            $totalCents = 0;')

# TermsSetAction reads InvoiceEngine::DEFAULT_CREDIT_LIMIT_CENTS
p = 'app/Modules/X-199/Actions/TermsSetAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;\nuse App\\Modules\\X199\\Domain\\InvoiceEngine;')
rep(p, 'final class TermsSetAction\n{', '''final class TermsSetAction
{
    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function defaultCreditLimitCents(): int
    {
        return $this->registry->int('invoices.default_credit_limit_cents', InvoiceEngine::DEFAULT_CREDIT_LIMIT_CENTS);
    }
''')
rep(p, 'int $creditLimitCents = 500000,', '?int $creditLimitCents = null,')
rep(p, '$term = CreditTerm::updateOrCreate(', '$creditLimitCents ??= $this->defaultCreditLimitCents();\n        $term = CreditTerm::updateOrCreate(')


# PlanProposeAction X-165
p = 'app/Modules/X-165/Actions/PlanProposeAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class PlanProposeAction\n{', '''final class PlanProposeAction
{
    public const DEFAULT_INTERVAL_MONTHS = 12;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function defaultIntervalMonths(): int
    {
        return $this->registry->int('plans.default_interval_months', self::DEFAULT_INTERVAL_MONTHS);
    }
''')
rep(p, 'int $intervalMonths = 12', '?int $intervalMonths = null')
rep(p, 'if (! in_array($intervalMonths, [1, 12]', '$intervalMonths ??= $this->defaultIntervalMonths();\n        if (! in_array($intervalMonths, [1, 12]')

# EmailWarmupAction C-Mail
p = 'app/Modules/C-Mail/Actions/EmailWarmupAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class EmailWarmupAction\n{', '''final class EmailWarmupAction
{
    public const JITTER_PCT = 20;
    public const DAILY_ALLOWANCE = 100;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function jitterPct(): int
    {
        return $this->registry->int('mail.warmup.jitter_pct', self::JITTER_PCT);
    }

    private function dailyAllowance(): int
    {
        return $this->registry->int('mail.warmup.daily_allowance', self::DAILY_ALLOWANCE);
    }
''')
rep(p, '$jitter = (int) ($allowance * 0.20);', '$jitter = (int) ($allowance * ($this->jitterPct() / 100));')
rep(p, '$allowance = 100;', '$allowance = $this->dailyAllowance();')
rep(p, 'public const JITTER_PCT = 20;', '') # remove old constant if it exists
rep(p, '    public const DAILY_ALLOWANCE = 100;\n\n    public function __construct', '    public function __construct')


# LinkPitchAction X-191
p = 'app/Modules/X-191/Actions/LinkPitchAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class LinkPitchAction\n{', '''final class LinkPitchAction
{
    public const MONTHLY_SEND_CEILING = 50;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function monthlySendCeiling(): int
    {
        return $this->registry->int('links.pitch.monthly_send_ceiling', self::MONTHLY_SEND_CEILING);
    }
''')
rep(p, 'public const MONTHLY_SEND_CEILING = 50;', '')
rep(p, '    public function __construct', '    public function __construct')
rep(p, 'self::MONTHLY_SEND_CEILING', '$this->monthlySendCeiling()')

# InternalLinkRenderAction X-176
p = 'app/Modules/X-176/Actions/InternalLinkRenderAction.php'
rep(p, 'use App\\Modules\\X176\\Models\\InternalLink;', 'use App\\Support\\DefaultsRegistry;\nuse App\\Modules\\X176\\Models\\InternalLink;')
rep(p, 'final class InternalLinkRenderAction\n{', '''final class InternalLinkRenderAction
{
    public const EMITTED_MAX = 20;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function emittedMax(): int
    {
        return $this->registry->int('links.internal.emitted_max', self::EMITTED_MAX);
    }
''')
rep(p, 'if (count($links) > 20)', 'if (count($links) > $this->emittedMax())')
rep(p, 'array_slice($links, 0, 20)', 'array_slice($links, 0, $this->emittedMax())')

# ConversionUploadEngine X-139
p = 'app/Modules/X-139/Domain/ConversionUploadEngine.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class ConversionUploadEngine\n{', '''final class ConversionUploadEngine
{
    public const ATTRIBUTION_WINDOW_DAYS = 90;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function attributionWindowDays(): int
    {
        return $this->registry->int('attribution.conversion.window_days', self::ATTRIBUTION_WINDOW_DAYS);
    }
''')
rep(p, '->subDays(90)', '->subDays($this->attributionWindowDays())')

# ConversionUploadAction reads the same key X-139
p = 'app/Modules/X-139/Actions/ConversionUploadAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;\nuse App\\Modules\\X139\\Domain\\ConversionUploadEngine;')
rep(p, 'final class ConversionUploadAction\n{', '''final class ConversionUploadAction
{
    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function attributionWindowDays(): int
    {
        return $this->registry->int('attribution.conversion.window_days', ConversionUploadEngine::ATTRIBUTION_WINDOW_DAYS);
    }
''')
# Wait, let's see what needs to be replaced in ConversionUploadAction
# It might have 90 hardcoded. Let's not replace yet. I'll check its content.

# SignalScoreAction X-136
p = 'app/Modules/X-136/Actions/SignalScoreAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class SignalScoreAction\n{', '''final class SignalScoreAction
{
    public const HIGH_INTENT_SCORE = 75.0;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function highIntentScore(): float
    {
        return $this->registry->float('signals.high_intent_score', self::HIGH_INTENT_SCORE);
    }
''')
# Need to check original constant if it existed and remove it, or just replace 75.0.

# ProviderHealthAction X-219
p = 'app/Modules/X-219/Actions/ProviderHealthAction.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class ProviderHealthAction\n{', '''final class ProviderHealthAction
{
    public const DEGRADED_ERROR_RATE_PCT = 50;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function degradedErrorRatePct(): int
    {
        return $this->registry->int('ai.provider.degraded_error_rate_pct', self::DEGRADED_ERROR_RATE_PCT);
    }
''')
# Check original

