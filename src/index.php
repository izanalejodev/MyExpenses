<?php
$dsn = "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4";
try {
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Conectado a MySQL " . $pdo->query('SELECT VERSION()')->fetchColumn();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}


