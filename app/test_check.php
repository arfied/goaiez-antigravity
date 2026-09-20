<?php
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=goaiez_antig_test', 'goaiez_app', 'WyVIyiK8xm7b7xr4YnhXu71B');
$stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = 'portal_links'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
