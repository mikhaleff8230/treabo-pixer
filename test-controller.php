<?php
require_once 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';

echo "Testing PvzController...\n";
try {
    $controller = new Marvel\Http\Controllers\PvzController();
    echo "Controller created successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
