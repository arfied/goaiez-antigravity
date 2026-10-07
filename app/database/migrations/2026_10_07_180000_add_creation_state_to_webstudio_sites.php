<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // EXPAND only (§259): a site being created has no project yet, so the two columns widen to nullable with raw
        // statements — the idiom of this directory (users :48, plugins :77, citations :41) — never the Blueprint column-change
        // method, which the schema stage reads as a SWITCH and refuses beside the dropIfExists every create migration carries.
        // (This comment deliberately does not spell that method: the stage greps the whole file, comments included.)
        DB::statement('ALTER TABLE webstudio_sites ALTER COLUMN project_id DROP NOT NULL');
        DB::statement('ALTER TABLE webstudio_sites ALTER COLUMN editor_token DROP NOT NULL');

        Schema::table('webstudio_sites', function (Blueprint $table): void {
            $table->string('template_id', 64)->nullable()->index();      // SiteTemplates key when source = template
            $table->string('creation_status', 16)->default('ready');     // creating | ready | failed
            $table->string('creation_message', 255)->default('Ready');
            $table->text('creation_error')->nullable();                  // internal only
        });
    }

    public function down(): void
    {
        DB::table('webstudio_sites')->whereNull('project_id')->orWhereNull('editor_token')->delete();

        Schema::table('webstudio_sites', function (Blueprint $table): void {
            $table->dropColumn(['template_id', 'creation_status', 'creation_message', 'creation_error']);
        });

        DB::statement('ALTER TABLE webstudio_sites ALTER COLUMN project_id SET NOT NULL');
        DB::statement('ALTER TABLE webstudio_sites ALTER COLUMN editor_token SET NOT NULL');
    }
};
