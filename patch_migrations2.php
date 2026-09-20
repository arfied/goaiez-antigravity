<?php
$f2 = 'app/database/migrations/2026_09_02_090500_alter_x140_tables_for_scaffold.php';
$c2 = file_get_contents($f2);
$c2 = str_replace("\$table->renameColumn('name', 'topic_title');", "DB::statement('ALTER TABLE content_topics RENAME COLUMN name TO topic_title');", $c2);
$c2 = str_replace("\$table->renameColumn('intent', 'cluster_key');", "DB::statement('ALTER TABLE content_topics RENAME COLUMN intent TO cluster_key');", $c2);
$c2 = "<?php\nuse Illuminate\\Support\\Facades\\DB;\n" . substr($c2, 5);
file_put_contents($f2, $c2);
