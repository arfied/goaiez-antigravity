#!/bin/bash
sed -i 's/final class UnifiedInboxManager/final class UnifiedInboxManager\n{\n    public const TIER_HOT = 80;\n    public const TIER_WARM = 60;\n    public const TIER_COOL = 40;\n    public const TIER_COLD = 20;/' app/app/Modules/X-01/Domain/UnifiedInboxManager.php
sed -i 's/final class UnifiedInboxManager\n{/final class UnifiedInboxManager/' app/app/Modules/X-01/Domain/UnifiedInboxManager.php # wait, fixing the first sed
