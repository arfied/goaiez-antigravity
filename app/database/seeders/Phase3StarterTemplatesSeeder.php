<?php

namespace Database\Seeders;

use App\Modules\X116\Models\Template;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class Phase3StarterTemplatesSeeder extends Seeder
{
    public function run()
    {
        Tenancy::set(5); // Pilot tenant

        $templates = [
            ['business_id' => 5, 'industry_code' => 'clinic', 'funnel_type' => 'lead_gen', 'conversion_rate' => 0.12, 'design_tokens' => json_encode(['theme' => 'blue_medical', 'font' => 'sans-serif'])],
            ['business_id' => 5, 'industry_code' => 'trades', 'funnel_type' => 'quote_request', 'conversion_rate' => 0.08, 'design_tokens' => json_encode(['theme' => 'rugged_orange', 'font' => 'serif'])],
            ['business_id' => 5, 'industry_code' => 'salon', 'funnel_type' => 'booking', 'conversion_rate' => 0.15, 'design_tokens' => json_encode(['theme' => 'elegant_pink', 'font' => 'sans-serif'])],
            ['business_id' => 5, 'industry_code' => 'minimal', 'funnel_type' => 'portfolio', 'conversion_rate' => 0.05, 'design_tokens' => json_encode(['theme' => 'monochrome', 'font' => 'sans-serif'])],
            ['business_id' => 5, 'industry_code' => 'modern', 'funnel_type' => 'saas', 'conversion_rate' => 0.10, 'design_tokens' => json_encode(['theme' => 'vibrant_purple', 'font' => 'sans-serif'])],
        ];

        foreach ($templates as $t) {
            Template::create($t);
        }
    }
}
