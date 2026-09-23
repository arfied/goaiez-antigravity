import re

def process_file(path, replacements, constants=None, accessors=None):
    with open(path, 'r') as f:
        content = f.read()

    if 'use App\\Support\\DefaultsRegistry;' not in content:
        content = re.sub(r'(use [A-Za-z0-9\\]+;)', r'use App\\Support\\DefaultsRegistry;\n\1', content, count=1)

    if '__construct' in content:
        if 'DefaultsRegistry' not in content.split('__construct')[1].split(')')[0]:
            # Use string replacement instead of regex to avoid backslash issues
            parts = content.split('__construct')
            first_part = parts[0]
            rest = '__construct'.join(parts[1:])
            # find first closing parenthesis
            paren_idx = rest.find(')')
            if paren_idx != -1:
                args = rest[:paren_idx]
                new_args = args + (', ' if args.strip() and not args.endswith('(') else '') + 'private DefaultsRegistry $registry'
                new_args = new_args.replace('(, ', '(')
                content = first_part + '__construct' + new_args + rest[paren_idx:]
    else:
        inj = "\n    public function __construct(private DefaultsRegistry $registry)\n    {\n    }\n"
        content = content.replace('    public function', inj + '    public function', 1)
        if inj not in content:
            content = content.replace('}\n', inj + '}\n', 1)

    if constants and constants not in content:
        content = re.sub(r'(final class [A-Za-z0-9_]+[\n\s]*{)', r'\1\n' + constants + '\n', content, count=1)
    
    if accessors and accessors not in content:
        content = content.replace('    public function', accessors + '    public function', 1)

    for search, replace in replacements:
        if search in content:
            content = content.replace(search, replace)
        elif hasattr(search, 'pattern') and search.search(content):
            content = search.sub(replace, content)
        else:
            print(f"Warning: could not find {search} in {path}")
            
    with open(path, 'w') as f:
        f.write(content)

# 1. BillingLedgerEngine
process_file('app/Modules/C-Billing/Domain/BillingLedgerEngine.php', [
    ("'daily_topup_ceiling_cents' => 50000,", "'daily_topup_ceiling_cents' => $this->dailyTopupCeilingCents(),"),
    ("$aiEnabled = ($dayInCycle < 21);", "$aiEnabled = ($dayInCycle < $this->voicemailOnlyFromDay());"),
    ("$voicemailOnly = ($dayInCycle >= 21);", "$voicemailOnly = ($dayInCycle >= $this->voicemailOnlyFromDay());"),
    ("$dayInCycle < 21 => 'banner',", "$dayInCycle < $this->voicemailOnlyFromDay() => 'banner',")
], constants="""    public const DAILY_TOPUP_CEILING_CENTS = 50000;
    public const VOICEMAIL_ONLY_FROM_DAY = 21;""", accessors="""    private function dailyTopupCeilingCents(): int
    {
        return $this->registry->int('billing.topup.daily_ceiling_cents', self::DAILY_TOPUP_CEILING_CENTS);
    }

    private function voicemailOnlyFromDay(): int
    {
        return $this->registry->int('billing.cycle.voicemail_only_from_day', self::VOICEMAIL_ONLY_FROM_DAY);
    }
""")

# 2. InvoiceEngine
process_file('app/Modules/X-199/Domain/InvoiceEngine.php', [
    ("'credit_limit_cents' => 500000,", "'credit_limit_cents' => $this->defaultCreditLimitCents(),")
], constants="""    public const DEFAULT_CREDIT_LIMIT_CENTS = 500000;""", accessors="""    private function defaultCreditLimitCents(): int
    {
        return $this->registry->int('invoices.default_credit_limit_cents', self::DEFAULT_CREDIT_LIMIT_CENTS);
    }
""")

# 3. InvoiceDraftAction
process_file('app/Modules/X-199/Actions/InvoiceDraftAction.php', [
    ('public function handle(int $businessId, int $customerId, array $lines, int $dueDays = 30): Invoice', 'public function handle(int $businessId, int $customerId, array $lines, ?int $dueDays = null): Invoice'),
    ('$totalCents = 0;', '$dueDays ??= $this->defaultDueDays();\n            $totalCents = 0;')
], constants="""    public const DEFAULT_DUE_DAYS = 30;""", accessors="""    private function defaultDueDays(): int
    {
        return $this->registry->int('invoices.default_due_days', self::DEFAULT_DUE_DAYS);
    }
""")

# 4. TermsSetAction X-199
process_file('app/Modules/X-199/Actions/TermsSetAction.php', [
    ('int $creditLimitCents = 500000,', '?int $creditLimitCents = null,'),
    ('$term = CreditTerm::updateOrCreate(', '$creditLimitCents ??= $this->defaultCreditLimitCents();\n        $term = CreditTerm::updateOrCreate(')
], accessors="""    private function defaultCreditLimitCents(): int
    {
        return $this->registry->int('invoices.default_credit_limit_cents', \App\Modules\X199\Domain\InvoiceEngine::DEFAULT_CREDIT_LIMIT_CENTS);
    }
""")

# 5. PlanProposeAction X-165
process_file('app/Modules/X-165/Actions/PlanProposeAction.php', [
    ('int $intervalMonths = 12', '?int $intervalMonths = null'),
    ('if (! in_array($intervalMonths, [1, 12]', '$intervalMonths ??= $this->defaultIntervalMonths();\n        if (! in_array($intervalMonths, [1, 12]')
], constants="""    public const DEFAULT_INTERVAL_MONTHS = 12;""", accessors="""    private function defaultIntervalMonths(): int
    {
        return $this->registry->int('plans.default_interval_months', self::DEFAULT_INTERVAL_MONTHS);
    }
""")

