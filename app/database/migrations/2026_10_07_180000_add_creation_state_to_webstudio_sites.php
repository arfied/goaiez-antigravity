<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webstudio_sites', function (Blueprint $table): void {
            $table->string('project_id', 64)->nullable()->change();
            $table->text('editor_token')->nullable()->change();
            $table->string('template_id', 64)->nullable()->index();
            $table->string('creation_status', 16)->default('ready');
            $table->string('creation_message', 255)->default('Ready');
            $table->text('creation_error')->nullable();
        });
    }

    public function down(): void
    {
        DB::table('webstudio_sites')->whereNull('project_id')->delete();
        DB::table('webstudio_sites')->whereNull('editor_token')->delete();

        Schema::table('webstudio_sites', function (Blueprint $table): void {
            $table->string('project_id', 64)->nullable(false)->change();
            $table->text('editor_token')->nullable(false)->change();
            $table->dropColumn(['template_id', 'creation_status', 'creation_message', 'creation_error']);
        });
    }
};
