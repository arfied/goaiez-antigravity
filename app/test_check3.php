<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=goaiez_antig_test', 'goaiez_owner', 'RtWbH8suERzy9Aygscm42wrASClj4TQ0');
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
