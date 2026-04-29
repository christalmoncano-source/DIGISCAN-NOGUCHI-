<?php
require_once 'config/db.php';

$tables = ['books', 'reservations', 'borrowings', 'notifications'];
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } else {
        echo "Table $table does not exist.\n";
    }
    echo "\n";
}
?>
