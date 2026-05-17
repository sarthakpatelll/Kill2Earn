<?php
$host = 'localhost';
$dbname = 'kill2earn';
$username = 'root';
$password = '12/10/05';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if upi_id column exists
    $stmt = $pdo->query("DESCRIBE wallet_transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $upiColumnExists = false;
    $screenshotColumnExists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'upi_id') {
            $upiColumnExists = true;
        }
        if ($column['Field'] === 'payment_screenshot') {
            $screenshotColumnExists = true;
        }
    }

    if (!$upiColumnExists) {
        echo "upi_id column missing. Adding it now...\n";
        $pdo->exec("ALTER TABLE wallet_transactions ADD COLUMN upi_id VARCHAR(100) DEFAULT NULL");
        echo "upi_id column added successfully!\n";
    } else {
        echo "upi_id column already exists.\n";
    }

    if (!$screenshotColumnExists) {
        echo "payment_screenshot column missing. Adding it now...\n";
        $pdo->exec("ALTER TABLE wallet_transactions ADD COLUMN payment_screenshot VARCHAR(255) DEFAULT NULL");
        echo "payment_screenshot column added successfully!\n";
    } else {
        echo "payment_screenshot column already exists.\n";
    }

    // Show current table structure
    echo "\nCurrent wallet_transactions table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']} " . ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . " " . ($column['Default'] ? "DEFAULT '{$column['Default']}'" : '') . "\n";
    }

} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
