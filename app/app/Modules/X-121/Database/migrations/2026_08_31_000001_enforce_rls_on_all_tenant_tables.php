<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tables = DB::select(<<<'SQL'
            SELECT c.relname AS tablename
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN information_schema.columns col ON col.table_name = c.relname AND col.table_schema = 'public'
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND col.column_name = 'business_id'
              AND c.relname != 'jobs'
        SQL);

        foreach ($tables as $t) {
            $tableName = $t->tablename;
            DB::statement("ALTER TABLE {$tableName} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tableName} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tableName}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$tableName}
                    USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void {}
};
