<?php

declare(strict_types=1);

use App\Enums\IndustryFamily;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('industry_starting_points', function (Blueprint $t) {
            $t->jsonb('questions')->default('[]');
        });

        $seeds = [
            IndustryFamily::Trades->value => [
                ['key' => 'emergency_callouts', 'label' => 'Do you take emergency call-outs?', 'hint' => 'For example "24/7 for burst pipes", or leave it empty.', 'max' => 120, 'hero' => true],
                ['key' => 'free_quotes', 'label' => 'Do you quote for free?', 'hint' => 'Only if you really do. We never add it.', 'max' => 80],
                ['key' => 'guarantee', 'label' => 'Any guarantee on your work?', 'hint' => 'For example "12 months on parts and labour".', 'max' => 160],
                ['key' => 'call_out_area', 'label' => 'How far do you travel?', 'hint' => 'If it differs from "Where you work" above.', 'max' => 160],
            ],
            IndustryFamily::Auto->value => [
                ['key' => 'makes_specialised', 'label' => 'Makes you specialise in', 'hint' => 'For example "BMW, Audi, VW". Leave empty if you take everything.', 'max' => 160, 'hero' => true],
                ['key' => 'courtesy_car', 'label' => 'Do you offer a courtesy car?', 'hint' => 'Only if you have one.', 'max' => 80],
                ['key' => 'mot_or_inspection', 'label' => 'Do you do inspections or MOTs?', 'hint' => 'Say what you are approved for, in your words.', 'max' => 120],
                ['key' => 'warranty_on_repairs', 'label' => 'Warranty on repairs', 'hint' => 'For example "12 months or 12,000 miles".', 'max' => 120],
            ],
            IndustryFamily::Care->value => [
                ['key' => 'walk_ins', 'label' => 'Walk-ins or appointments?', 'hint' => 'For example "Appointments preferred, walk-ins when we can".', 'max' => 120, 'hero' => true],
                ['key' => 'first_visit', 'label' => 'What happens on a first visit?', 'hint' => 'Two or three sentences. It calms a nervous first-timer.', 'max' => 400],
                ['key' => 'who_you_see', 'label' => 'Do people see the same person each time?', 'hint' => 'Only if that is true.', 'max' => 120],
                ['key' => 'parking', 'label' => 'Parking', 'hint' => 'For example "Free out front" or "Street only".', 'max' => 120],
            ],
            IndustryFamily::Food->value => [
                ['key' => 'delivery_or_takeaway', 'label' => 'Delivery, takeaway, or eat in?', 'hint' => 'Say which you actually do.', 'max' => 120, 'hero' => true],
                ['key' => 'dietary', 'label' => 'Anything you cater for?', 'hint' => 'For example "Gluten-free and vegan options".', 'max' => 160],
                ['key' => 'book_a_table', 'label' => 'Do you take bookings?', 'hint' => 'For example "Tables of six or more only".', 'max' => 120],
                ['key' => 'busy_times', 'label' => 'Best time to come', 'hint' => 'For example "Quiet before six".', 'max' => 120],
            ],
            IndustryFamily::Office->value => [
                ['key' => 'free_first_consultation', 'label' => 'Is the first consultation free?', 'hint' => 'Only if it is.', 'max' => 80, 'hero' => true],
                ['key' => 'who_you_help', 'label' => 'Who you usually help', 'hint' => 'For example "Families and small businesses locally".', 'max' => 200],
                ['key' => 'how_you_charge', 'label' => 'How you charge', 'hint' => 'For example "Fixed fee, agreed up front". Not a price.', 'max' => 160],
                ['key' => 'remote_or_in_person', 'label' => 'Remote, in person, or both?', 'hint' => '', 'max' => 100],
            ],
            IndustryFamily::Medspa->value => [
                ['key' => 'memberships', 'label' => 'Do you offer memberships?', 'hint' => 'For example "Monthly plan, cancel any time". No prices here.', 'max' => 160, 'hero' => true],
                ['key' => 'consultation_first', 'label' => 'Is a consultation required first?', 'hint' => 'Say so plainly if it is.', 'max' => 120],
                ['key' => 'who_treats', 'label' => 'Who carries out treatments?', 'hint' => 'For example "A registered nurse". Only what you can stand behind.', 'max' => 160],
                ['key' => 'aftercare', 'label' => 'Aftercare you provide', 'hint' => '', 'max' => 200],
            ],
        ];

        foreach ($seeds as $family => $questions) {
            DB::table('industry_starting_points')
                ->where('family', $family)
                ->update(['questions' => json_encode($questions)]);
        }
    }

    public function down(): void
    {
        Schema::table('industry_starting_points', function (Blueprint $t) {
            $t->dropColumn('questions');
        });
    }
};
