const fs = require('fs');
const path = require('path');

function rep(file, search, replace) {
    let content = fs.readFileSync(file, 'utf8');
    if (!content.includes(search)) {
        console.log(`Warning: could not find [${search}] in ${file}`);
    }
    content = content.split(search).join(replace);
    fs.writeFileSync(file, content);
}

function processClass(file, constants, accessors) {
    let content = fs.readFileSync(file, 'utf8');
    if (!content.includes('use App\\Support\\DefaultsRegistry;')) {
        content = content.replace(/(use [A-Za-z0-9\\]+;)/, 'use App\\Support\\DefaultsRegistry;\n$1');
    }
    
    // add constants
    if (constants && !content.includes(constants.trim().split('\n')[0])) {
        content = content.replace(/(final class [A-Za-z0-9_]+[\n\s]*{)/, `$1\n${constants}\n`);
    }

    // add constructor and accessors
    if (!content.includes('__construct')) {
        let inj = `\n    public function __construct(private DefaultsRegistry $registry)\n    {\n    }\n`;
        content = content.replace(/([ \t]*public function)/, inj + `$1`);
    } else {
        if (!content.includes('DefaultsRegistry $registry')) {
            content = content.replace(/(__construct\s*\([^)]*)/, `$1, private DefaultsRegistry $registry`);
            content = content.replace('(, ', '(');
        }
    }
    
    if (accessors && !content.includes(accessors.trim().split('\n')[0])) {
        content = content.replace(/([ \t]*public function)/, `${accessors}$1`);
    }

    fs.writeFileSync(file, content);
}

// 1. BillingLedgerEngine
let f = 'app/Modules/C-Billing/Domain/BillingLedgerEngine.php';
processClass(f, 
    `    public const DAILY_TOPUP_CEILING_CENTS = 50000;\n    public const VOICEMAIL_ONLY_FROM_DAY = 21;`,
    `    private function dailyTopupCeilingCents(): int\n    {\n        return $this->registry->int('billing.topup.daily_ceiling_cents', self::DAILY_TOPUP_CEILING_CENTS);\n    }\n\n    private function voicemailOnlyFromDay(): int\n    {\n        return $this->registry->int('billing.cycle.voicemail_only_from_day', self::VOICEMAIL_ONLY_FROM_DAY);\n    }\n`
);
rep(f, "'daily_topup_ceiling_cents' => 50000,", "'daily_topup_ceiling_cents' => $this->dailyTopupCeilingCents(),");
rep(f, "$aiEnabled = ($dayInCycle < 21);", "$aiEnabled = ($dayInCycle < $this->voicemailOnlyFromDay());");
rep(f, "$voicemailOnly = ($dayInCycle >= 21);", "$voicemailOnly = ($dayInCycle >= $this->voicemailOnlyFromDay());");
rep(f, "$dayInCycle < 21 => 'banner',", "$dayInCycle < $this->voicemailOnlyFromDay() => 'banner',");

// 2. InvoiceEngine
f = 'app/Modules/X-199/Domain/InvoiceEngine.php';
processClass(f, 
    `    public const DEFAULT_CREDIT_LIMIT_CENTS = 500000;`,
    `    public function defaultCreditLimitCents(): int\n    {\n        return $this->registry->int('invoices.default_credit_limit_cents', self::DEFAULT_CREDIT_LIMIT_CENTS);\n    }\n`
);
rep(f, "'credit_limit_cents' => 500000,", "'credit_limit_cents' => $this->defaultCreditLimitCents(),");

// 3. InvoiceDraftAction
f = 'app/Modules/X-199/Actions/InvoiceDraftAction.php';
processClass(f, 
    `    public const DEFAULT_DUE_DAYS = 30;`,
    `    private function defaultDueDays(): int\n    {\n        return $this->registry->int('invoices.default_due_days', self::DEFAULT_DUE_DAYS);\n    }\n`
);
rep(f, 'public function handle(int $businessId, int $customerId, array $lines, int $dueDays = 30): Invoice', 'public function handle(int $businessId, int $customerId, array $lines, ?int $dueDays = null): Invoice');
rep(f, '$totalCents = 0;', '$dueDays ??= $this->defaultDueDays();\n            $totalCents = 0;');

// 4. TermsSetAction
f = 'app/Modules/X-199/Actions/TermsSetAction.php';
// It reads InvoiceEngine's constant.
processClass(f, '', 
    `    private function defaultCreditLimitCents(): int\n    {\n        return $this->registry->int('invoices.default_credit_limit_cents', \\App\\Modules\\X199\\Domain\\InvoiceEngine::DEFAULT_CREDIT_LIMIT_CENTS);\n    }\n`
);
rep(f, 'int $creditLimitCents = 500000,', '?int $creditLimitCents = null,');
rep(f, '$term = CreditTerm::updateOrCreate(', '$creditLimitCents ??= $this->defaultCreditLimitCents();\n        $term = CreditTerm::updateOrCreate(');

// 5. PlanProposeAction X-165
f = 'app/Modules/X-165/Actions/PlanProposeAction.php';
processClass(f,
    `    public const DEFAULT_INTERVAL_MONTHS = 12;`,
    `    private function defaultIntervalMonths(): int\n    {\n        return $this->registry->int('plans.default_interval_months', self::DEFAULT_INTERVAL_MONTHS);\n    }\n`
);
rep(f, 'int $intervalMonths = 12', '?int $intervalMonths = null');
rep(f, 'if (! in_array($intervalMonths, [1, 12]', '$intervalMonths ??= $this->defaultIntervalMonths();\n        if (! in_array($intervalMonths, [1, 12]');

// 6. EmailWarmupAction C-Mail
f = 'app/Modules/C-Mail/Actions/EmailWarmupAction.php';
processClass(f,
    `    public const DAILY_ALLOWANCE = 100;`,
    `    private function jitterPct(): int\n    {\n        return $this->registry->int('mail.warmup.jitter_pct', self::JITTER_PCT);\n    }\n\n    private function dailyAllowance(): int\n    {\n        return $this->registry->int('mail.warmup.daily_allowance', self::DAILY_ALLOWANCE);\n    }\n`
);
rep(f, '$jitter = (int) ($allowance * 0.20);', '$jitter = (int) ($allowance * ($this->jitterPct() / 100));');
rep(f, '$allowance = 100;', '$allowance = $this->dailyAllowance();');

// 7. LinkPitchAction X-191
f = 'app/Modules/X-191/Actions/LinkPitchAction.php';
processClass(f,
    ``,
    `    private function monthlySendCeiling(): int\n    {\n        return $this->registry->int('links.pitch.monthly_send_ceiling', self::MONTHLY_SEND_CEILING);\n    }\n`
);
rep(f, 'self::MONTHLY_SEND_CEILING', '$this->monthlySendCeiling()');
rep(f, "return $this->registry->int('links.pitch.monthly_send_ceiling', self::MONTHLY_SEND_CEILING);\n    }\n\n    private function monthlySendCeiling(): int\n    {\n        return $this->registry->int('links.pitch.monthly_send_ceiling', $this->monthlySendCeiling());\n    }", "return $this->registry->int('links.pitch.monthly_send_ceiling', self::MONTHLY_SEND_CEILING);\n    }");

// 8. InternalLinkRenderAction X-176
f = 'app/Modules/X-176/Actions/InternalLinkRenderAction.php';
processClass(f,
    `    public const EMITTED_MAX = 20;`,
    `    private function emittedMax(): int\n    {\n        return $this->registry->int('links.internal.emitted_max', self::EMITTED_MAX);\n    }\n`
);
rep(f, 'if (count($links) > 20)', 'if (count($links) > $this->emittedMax())');
rep(f, 'array_slice($links, 0, 20)', 'array_slice($links, 0, $this->emittedMax())');

// 9. ConversionUploadEngine X-139
f = 'app/Modules/X-139/Domain/ConversionUploadEngine.php';
processClass(f,
    `    public const ATTRIBUTION_WINDOW_DAYS = 90;`,
    `    public function attributionWindowDays(): int\n    {\n        return $this->registry->int('attribution.conversion.window_days', self::ATTRIBUTION_WINDOW_DAYS);\n    }\n`
);
rep(f, '->subDays(90)', '->subDays($this->attributionWindowDays())');

// 10. ConversionUploadAction X-139
f = 'app/Modules/X-139/Actions/ConversionUploadAction.php';
processClass(f,
    ``,
    `    private function attributionWindowDays(): int\n    {\n        return $this->registry->int('attribution.conversion.window_days', \\App\\Modules\\X139\\Domain\\ConversionUploadEngine::ATTRIBUTION_WINDOW_DAYS);\n    }\n`
);
rep(f, '->subDays(90)', '->subDays($this->attributionWindowDays())');

// 11. SignalScoreAction X-136
f = 'app/Modules/X-136/Actions/SignalScoreAction.php';
processClass(f,
    `    public const HIGH_INTENT_SCORE = 75.0;`,
    `    private function highIntentScore(): float\n    {\n        return $this->registry->float('signals.high_intent_score', self::HIGH_INTENT_SCORE);\n    }\n`
);
rep(f, '>= 75.0', '>= $this->highIntentScore()');
// Actually, let's look at the file after script runs to make sure I caught 75.0, or I can replace '75.0' -> '$this->highIntentScore()'
rep(f, ' 75.0', ' $this->highIntentScore()');

// 12. ProviderHealthAction X-219
f = 'app/Modules/X-219/Actions/ProviderHealthAction.php';
processClass(f,
    `    public const DEGRADED_ERROR_RATE_PCT = 50;`,
    `    private function degradedErrorRatePct(): int\n    {\n        return $this->registry->int('ai.provider.degraded_error_rate_pct', self::DEGRADED_ERROR_RATE_PCT);\n    }\n`
);
rep(f, '> 50)', '> $this->degradedErrorRatePct())');
rep(f, ' 50', ' $this->degradedErrorRatePct()');

