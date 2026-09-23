import os
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

# 1. BillingLedgerEngine
p = 'app/Modules/C-Billing/Domain/BillingLedgerEngine.php'
rep(p, 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Support\\DefaultsRegistry;\nuse Illuminate\\Support\\Facades\\DB;')
rep(p, 'final class BillingLedgerEngine\n{', '''final class BillingLedgerEngine
{
    public const DAILY_TOPUP_CEILING_CENTS = 50000;
    public const VOICEMAIL_ONLY_FROM_DAY = 21;

    public function __construct(private DefaultsRegistry $registry)
    {
    }

    private function dailyTopupCeilingCents(): int
    {
        return $this->registry->int('billing.topup.daily_ceiling_cents', self::DAILY_TOPUP_CEILING_CENTS);
    }

    private function voicemailOnlyFromDay(): int
    {
        return $this->registry->int('billing.cycle.voicemail_only_from_day', self::VOICEMAIL_ONLY_FROM_DAY);
    }
''')
rep(p, "'daily_topup_ceiling_cents' => 50000,", "'daily_topup_ceiling_cents' => $this->dailyTopupCeilingCents(),")
rep(p, "$aiEnabled = ($dayInCycle < 21);", "$aiEnabled = ($dayInCycle < $this->voicemailOnlyFromDay());")
rep(p, "$voicemailOnly = ($dayInCycle >= 21);", "$voicemailOnly = ($dayInCycle >= $this->voicemailOnlyFromDay());")
rep(p, "$dayInCycle < 21 => 'banner',", "$dayInCycle < $this->voicemailOnlyFromDay() => 'banner',")


# 2. InvoiceEngine
p = 'app/Modules/X-199/Domain/InvoiceEngine.php'
rep(p, 'use App\\Modules\\X199\\Events\\InvoicePaid;', 'use App\\Support\\DefaultsRegistry;\nuse App\\Modules\\X199\\Events\\InvoicePaid;')
rep(p, 'final class InvoiceEngine\n{', '''final class InvoiceEngine
{
    public const DEFAULT_CREDIT_LIMIT_CENTS = 500000;

    public function __construct(private GatewayEngine $gateway, private DefaultsRegistry $registry)
    {
    }

    private function defaultCreditLimitCents(): int
    {
        return $this->registry->int('invoices.default_credit_limit_cents', self::DEFAULT_CREDIT_LIMIT_CENTS);
    }
''')
# Need to check original constructor if it existed.
# Wait, InvoiceEngine has a constructor?
