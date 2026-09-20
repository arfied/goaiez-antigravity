<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=goaiez_antig_test', 'goaiez_app', 'WyVIyiK8xm7b7xr4YnhXu71B');
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
