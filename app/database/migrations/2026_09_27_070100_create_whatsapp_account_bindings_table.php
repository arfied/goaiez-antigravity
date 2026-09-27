<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_account_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('account_ref')->unique();
            $table->string('profile_ref');
            $table->timestamp('revocation_owed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_account_bindings');
    }
};
