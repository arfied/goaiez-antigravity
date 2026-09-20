<?php
$f1 = 'app/database/migrations/2026_09_02_085213_alter_webhook_secret_to_text.php';
$c1 = file_get_contents($f1);
$c1 = str_replace("\$table->text('secret')->change();", "DB::statement('ALTER TABLE webhook_subscriptions ALTER COLUMN secret TYPE text');", $c1);
$c1 = str_replace("\$table->string('secret')->change();", "DB::statement('ALTER TABLE webhook_subscriptions ALTER COLUMN secret TYPE character varying(255)');", $c1);
$c1 = "<?php\nuse Illuminate\\Support\\Facades\\DB;\n" . substr($c1, 5);
file_put_contents($f1, $c1);

$f2 = 'app/database/migrations/2026_09_02_090500_alter_x140_tables_for_scaffold.php';
$c2 = file_get_contents($f2);
// Let's see what is inside $f2
