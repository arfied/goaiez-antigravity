<?php
$content = file_get_contents('app/app/Support/DefaultsManifest.php');
$keys = <<<KEYS
            'crm.lead_score.tier_hot' => [
                'seed' => \App\Modules\X01\Domain\UnifiedInboxManager::TIER_HOT,
                'group' => 'Marketing',
                'description' => 'Lead score hot tier.',
            ],
            'crm.lead_score.tier_warm' => [
                'seed' => \App\Modules\X01\Domain\UnifiedInboxManager::TIER_WARM,
                'group' => 'Marketing',
                'description' => 'Lead score warm tier.',
            ],
            'crm.lead_score.tier_cool' => [
                'seed' => \App\Modules\X01\Domain\UnifiedInboxManager::TIER_COOL,
                'group' => 'Marketing',
                'description' => 'Lead score cool tier.',
            ],
            'crm.lead_score.tier_cold' => [
                'seed' => \App\Modules\X01\Domain\UnifiedInboxManager::TIER_COLD,
                'group' => 'Marketing',
                'description' => 'Lead score cold tier.',
            ],
            'affiliate.tier.gold_referrals' => [
                'seed' => \App\Modules\X205\Domain\AffiliateEngine::GOLD_REFERRALS,
                'group' => 'Affiliate',
                'description' => 'Affiliate gold tier referrals count.',
            ],
            'affiliate.tier.silver_referrals' => [
                'seed' => \App\Modules\X205\Domain\AffiliateEngine::SILVER_REFERRALS,
                'group' => 'Affiliate',
                'description' => 'Affiliate silver tier referrals count.',
            ],
            'affiliate.cookie_lifetime_days' => [
                'seed' => \App\Modules\X205\Domain\AffiliateEngine::COOKIE_LIFETIME_DAYS,
                'group' => 'Affiliate',
                'description' => 'Affiliate cookie lifetime days.',
            ],
            'sites.deploy.speed_budget_ms' => [
                'seed' => \App\Modules\X157\Actions\EdgeDeployAction::SPEED_BUDGET_MS,
                'group' => 'Content',
                'description' => 'Deploy speed budget ms.',
            ],
            'sites.deploy.pricebook_items_max' => [
                'seed' => \App\Modules\X157\Actions\EdgeDeployAction::PRICEBOOK_ITEMS_MAX,
                'group' => 'Content',
                'description' => 'Deploy pricebook items max.',
            ],
KEYS;

$content = str_replace(
    "'billing.currency' => [",
    \$keys . "\n            'billing.currency' => [",
    \$content
);

file_put_contents('app/app/Support/DefaultsManifest.php', \$content);
