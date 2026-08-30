<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $dbStatus = 'Operational';
    $migrationsCount = 0;
    $tablesCount = 0;

    try {
        $migrationsCount = DB::table('migrations')->count();
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        $tablesCount = count($tables);
    } catch (\Throwable $e) {
        $dbStatus = 'Unavailable: ' . $e->getMessage();
    }

    $modulesCount = 124;
    $wavesCount = 31;
    $journeysCount = 12;
    $capabilitiesCount = 966;

    $categories = [
        [
            'name' => 'Core AI & Agent Intelligence',
            'badge' => 'Intelligence',
            'badge_bg' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            'modules' => [
                ['id' => 'C-Ai', 'name' => 'AI Core & Provider Router (R237 Complex Slot)'],
                ['id' => 'C-Agent', 'name' => 'Autonomous Agent Loop & Policy Enforcer'],
                ['id' => 'X-219', 'name' => 'Model Roster & Tiering Registry'],
                ['id' => 'X-220', 'name' => 'Prompt Golden Evaluation Dataset'],
                ['id' => 'X-149', 'name' => 'Online Quality Eval & Assertion Matrix'],
                ['id' => 'X-124', 'name' => 'Assistant Knowledge Index & Vector Retrieval'],
            ]
        ],
        [
            'name' => 'Transports & Omnichannel Messaging',
            'badge' => 'Transports',
            'badge_bg' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'modules' => [
                ['id' => 'C-Telephony', 'name' => 'Carrier Voice Router & SIP Trunks'],
                ['id' => 'C-Sms', 'name' => 'SMS Gateway & 10DLC Brand Registration'],
                ['id' => 'C-Mail', 'name' => 'Transactional SES & Inbound Push'],
                ['id' => 'C-Whatsapp', 'name' => 'WhatsApp Cloud Business Transport'],
                ['id' => 'X-66', 'name' => 'Realtime Voice Streaming (LiveKit WebRTC)'],
                ['id' => 'X-147', 'name' => 'RCS Messaging & Rich Cards'],
                ['id' => 'X-200', 'name' => 'Outbound Progressive Dialer'],
            ]
        ],
        [
            'name' => 'Domain Core & Event Spine',
            'badge' => 'Spine',
            'badge_bg' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            'modules' => [
                ['id' => 'X-121', 'name' => 'Universal Noun Model & Tenant Aggregate'],
                ['id' => 'X-122', 'name' => 'CQRS Action Catalog (Command & Query)'],
                ['id' => 'X-123', 'name' => 'Transactional Event Bus & Sockets'],
                ['id' => 'X-126', 'name' => 'Capability Engine & Refusal Guardrails'],
                ['id' => 'X-127', 'name' => 'TenantZero Multi-Tenancy & RLS Isolation'],
                ['id' => 'X-128', 'name' => 'Multi-Tenant Permission Matrix'],
            ]
        ],
        [
            'name' => 'Billing, Payments & Invoicing',
            'badge' => 'Financial',
            'badge_bg' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            'modules' => [
                ['id' => 'C-Billing', 'name' => 'Multi-Provider Billing Core & Credit Ledgers'],
                ['id' => 'X-198', 'name' => 'Stripe & Authorize.Net Dual Payment Gateways'],
                ['id' => 'X-199', 'name' => 'Invoicing & Automated Statement Engine'],
                ['id' => 'X-211', 'name' => 'Accounts Receivable & Dunning Sequences'],
                ['id' => 'X-170', 'name' => 'Commission & Affiliate Split Calculations'],
                ['id' => 'X-214', 'name' => 'Dynamic Card Surcharge & Compliance'],
            ]
        ],
        [
            'name' => 'Reviews & Reputation Engine',
            'badge' => 'Reputation',
            'badge_bg' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            'modules' => [
                ['id' => 'C-Reviews', 'name' => 'Review Ingestion & Sentiment Pipeline'],
                ['id' => 'X-177', 'name' => 'Google Business Profile (GBP) Integration'],
                ['id' => 'X-181', 'name' => 'QA Ticket & Negative Feedback Escalation'],
                ['id' => 'X-110', 'name' => 'Visitor Attribution Pixel & Script Delivery'],
                ['id' => 'X-137', 'name' => 'Dynamic Number Insertion (DNI Pool)'],
            ]
        ],
        [
            'name' => 'Field Operations & Dispatch',
            'badge' => 'Operations',
            'badge_bg' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
            'modules' => [
                ['id' => 'X-162', 'name' => 'Smart Dispatch & Multi-Tech Routing'],
                ['id' => 'X-163', 'name' => 'Dynamic Pricebook & Tier Matrix'],
                ['id' => 'X-164', 'name' => 'Realtime Estimate Builder & Approvals'],
                ['id' => 'X-108', 'name' => 'Appointment Scheduling & Capacity Planning'],
                ['id' => 'X-167', 'name' => 'Truck Inventory & Stock Replenishment'],
                ['id' => 'X-168', 'name' => 'Technician Timesheets & Geo-fence Verification'],
            ]
        ],
        [
            'name' => 'Demand Generation & Growth Campaigns',
            'badge' => 'Growth',
            'badge_bg' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            'modules' => [
                ['id' => 'X-130', 'name' => 'Demand Forecasting & Seasonal Analysis'],
                ['id' => 'X-138', 'name' => 'Multi-Touch Revenue Attribution'],
                ['id' => 'X-139', 'name' => 'Autonomous Ad Campaign Engine'],
                ['id' => 'X-180', 'name' => 'Industry Vertical Content Packs'],
                ['id' => 'X-186', 'name' => 'Autonomous Drip Sequences & Win-backs'],
            ]
        ]
    ];

    return view('welcome', compact(
        'dbStatus',
        'migrationsCount',
        'tablesCount',
        'modulesCount',
        'wavesCount',
        'journeysCount',
        'capabilitiesCount',
        'categories'
    ));
});
