<?php

// 1. Define the database filename
$dbName = 'device_activation_credentials.sqlite';

try {
    // 2. Attempt to create a new PDO SQLite connection
    $pdo = new PDO('sqlite:' . $dbName);
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Successfully connected to the database: $dbName\n";

    // 4. Define CREATE TABLE SQL statements

    // Users table
    $sqlUsers = "
    CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        apple_id TEXT NOT NULL UNIQUE,
        hashed_password TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";

    // Devices table
    $sqlDevices = "
    CREATE TABLE IF NOT EXISTS devices (
        device_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        udid TEXT UNIQUE,
        serial_number TEXT,
        product_type TEXT,
        activation_state TEXT,
        last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (user_id)
    );";

    // 5. Use $pdo->exec() for table creation queries

    $pdo->exec($sqlUsers);
    echo "Table 'users' processed successfully (created or already exists).\n";

    $pdo->exec($sqlDevices);
    echo "Table 'devices' processed successfully (created or already exists).\n";

} catch (PDOException $e) {
    // 3. Handle PDO connection errors
    echo "Error connecting to or working with the database: " . $e->getMessage() . "\n";
} finally {
    // 7. Close the database connection
    if (isset($pdo)) {
        $pdo = null;
        echo "Database connection closed.\n";
    }
}

?>
