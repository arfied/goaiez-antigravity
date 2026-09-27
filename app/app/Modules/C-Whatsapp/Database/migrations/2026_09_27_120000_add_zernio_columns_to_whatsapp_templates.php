<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('provider_template_ref')->nullable()->index();
            $table->string('status_reason', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('status_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn([
                'provider_template_ref',
                'status_reason',
                'submitted_at',
                'status_updated_at',
            ]);
        });
    }
};
