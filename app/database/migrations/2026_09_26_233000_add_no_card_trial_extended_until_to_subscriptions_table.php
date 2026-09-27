<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscriptions', 'no_card_trial_extended_until')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('no_card_trial_extended_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'no_card_trial_extended_until')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('no_card_trial_extended_until');
            });
        }
    }
};
