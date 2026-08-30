<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_steps')) {
            Schema::table('campaign_steps', function (Blueprint $table): void {
                if (! Schema::hasColumn('campaign_steps', 'person_id')) {
                    $table->unsignedBigInteger('person_id')->nullable()->index();
                }
                if (! Schema::hasColumn('campaign_steps', 'recipient')) {
                    $table->string('recipient')->nullable();
                }
                if (! Schema::hasColumn('campaign_steps', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable();
                }
                if (! Schema::hasColumn('campaign_steps', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
