<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('industry_starting_points', function (Blueprint $t) {
            $t->id();
            $t->string('family', 16)->unique();       // IndustryFamily value
            $t->jsonb('palette');                     // flat map: surface, ink, primary, accent — six-digit hex
            $t->jsonb('type_pairing');                // flat map: heading, body — CSS font-family stacks
            $t->jsonb('section_order');               // ordered list of block types for the home page
            $t->timestamps();
        });

        // A platform table, no business_id, no RLS (precedent: legal_documents).
        DB::table('industry_starting_points')->insertOrIgnore([
            [
                'family' => 'trades',
                'palette' => json_encode(['surface' => '#f6f7f9', 'ink' => '#16202b', 'primary' => '#0f5f9c', 'accent' => '#e07a1f']),
                'type_pairing' => json_encode(['heading' => "Georgia, 'Times New Roman', serif", 'body' => "system-ui, -apple-system, 'Segoe UI', sans-serif"]),
                'section_order' => json_encode(['hero', 'booking_button', 'services', 'reviews_strip', 'gallery', 'about', 'faq', 'booking_form', 'contact', 'form']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'family' => 'auto',
                'palette' => json_encode(['surface' => '#f4f5f7', 'ink' => '#111827', 'primary' => '#1f2937', 'accent' => '#d97706']),
                'type_pairing' => json_encode(['heading' => "'Trebuchet MS', Arial, sans-serif", 'body' => "system-ui, -apple-system, 'Segoe UI', sans-serif"]),
                'section_order' => json_encode(['hero', 'services', 'booking_button', 'reviews_strip', 'about', 'gallery', 'faq', 'booking_form', 'contact', 'form']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'family' => 'care',
                'palette' => json_encode(['surface' => '#fbf7f4', 'ink' => '#2a2320', 'primary' => '#8a4b6e', 'accent' => '#c9a24d']),
                'type_pairing' => json_encode(['heading' => "Georgia, 'Times New Roman', serif", 'body' => "'Helvetica Neue', Arial, sans-serif"]),
                'section_order' => json_encode(['hero', 'gallery', 'services', 'booking_button', 'reviews_strip', 'about', 'team', 'faq', 'booking_form', 'contact', 'form']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'family' => 'food',
                'palette' => json_encode(['surface' => '#fffdf8', 'ink' => '#1f1a14', 'primary' => '#9b2c2c', 'accent' => '#d69e2e']),
                'type_pairing' => json_encode(['heading' => "Georgia, 'Times New Roman', serif", 'body' => "system-ui, -apple-system, 'Segoe UI', sans-serif"]),
                'section_order' => json_encode(['hero', 'gallery', 'about', 'services', 'reviews_strip', 'booking_button', 'faq', 'booking_form', 'contact', 'form']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'family' => 'office',
                'palette' => json_encode(['surface' => '#ffffff', 'ink' => '#1a2b49', 'primary' => '#1a2b49', 'accent' => '#2b6cb0']),
                'type_pairing' => json_encode(['heading' => "Georgia, 'Times New Roman', serif", 'body' => "'Helvetica Neue', Arial, sans-serif"]),
                'section_order' => json_encode(['hero', 'about', 'services', 'team', 'reviews_strip', 'booking_button', 'faq', 'booking_form', 'contact', 'form']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'family' => 'medspa',
                'palette' => json_encode(['surface' => '#f7f5f2', 'ink' => '#24302e', 'primary' => '#2f6f68', 'accent' => '#b08968']),
                'type_pairing' => json_encode(['heading' => "Georgia, 'Times New Roman', serif", 'body' => "system-ui, -apple-system, 'Segoe UI', sans-serif"]),
                'section_order' => json_encode(['hero', 'gallery', 'services', 'booking_button', 'about', 'reviews_strip', 'team', 'faq', 'booking_form', 'contact', 'form']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('industry_starting_points');
    }
};
