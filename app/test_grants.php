<?php
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=goaiez_antig_test', 'goaiez_app', 'WyVIyiK8xm7b7xr4YnhXu71B');
$stmt = $pdo->query("SELECT grantee, privilege_type FROM information_schema.role_table_grants WHERE table_name = 'portal_links'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
