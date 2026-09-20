<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=goaiez_antig', 'goaiez_app', 'WyVIyiK8xm7b7xr4YnhXu71B');
    echo "SUCCESS APP PROD\n";
} catch (Exception $e) {
    echo "FAIL APP: " . $e->getMessage() . "\n";
}
