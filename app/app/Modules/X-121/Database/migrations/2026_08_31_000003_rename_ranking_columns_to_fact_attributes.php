<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RENAMES = [
        ['demand_series', 'demand_score', 'demand_index'],
        ['scorecards', 'average_review_score', 'average_rating'],
        ['deployments', 'speed_score', 'speed_index'],
        ['enrichment_fields', 'confidence_score', 'confidence_rate'],
        ['lead_scores', 'score', 'lead_rating'],
        ['link_targets', 'da_score', 'domain_authority'],
        ['content_topics', 'similarity_score', 'similarity_rate'],
        ['audits', 'overall_score', 'overall_rating'],
        ['resolution_evidence', 'confidence_score', 'confidence_rate'],
        ['person_links', 'confidence_score', 'confidence_rate'],
        ['churn_scores', 'risk_score', 'risk_level'],
        ['automation_runs', 'quality_score', 'quality_rating'],
        ['content_quality_checks', 'uniqueness_score', 'uniqueness_rate'],
        ['content_quality_checks', 'readability_score', 'readability_rate'],
        ['growth_pages', 'quality_score', 'quality_rating'],
        ['phone_numbers', 'health_score', 'health_index'],
        ['boost_score_history', 'score', 'boost_value'],
        ['number_health_daily', 'score', 'health_value'],
        ['public_audits', 'score', 'audit_rating'],
        ['template_matches', 'match_score', 'match_rate'],
        ['directory_memberships', 'rank_score', 'directory_index'],
        ['qa_scorecards', 'score', 'qa_rating'],
        ['cwv_samples', 'cls_score', 'cls_value'],
        ['signal_scores', 'score', 'signal_value'],
        ['person_interests', 'confidence_score', 'confidence_rate'],
        ['templates', 'conversion_score', 'conversion_rate'],
        ['accounting_sync_conflicts', 'confidence_score', 'confidence_rate'],
        ['l1_events', 'bot_score', 'bot_probability'],
        ['wizard_progress', 'setup_score', 'setup_progress'],
        ['locations', 'boost_score', 'boost_rating'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as [$table, $oldCol, $newCol]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $oldCol)) {
                if (! Schema::hasColumn($table, $newCol)) {
                    DB::statement("ALTER TABLE {$table} RENAME COLUMN {$oldCol} TO {$newCol}");
                } else {
                    DB::statement("ALTER TABLE {$table} DROP COLUMN {$oldCol}");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as [$table, $oldCol, $newCol]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $newCol) && ! Schema::hasColumn($table, $oldCol)) {
                DB::statement("ALTER TABLE {$table} RENAME COLUMN {$newCol} TO {$oldCol}");
            }
        }
    }
};
