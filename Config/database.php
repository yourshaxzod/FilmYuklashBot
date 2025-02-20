<?php

function connectDatabase(): PDO
{
    try {
        $db = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $db;
    } catch (PDOException $e) {
        die("Database ulanishda xatolik: " . $e->getMessage());
    }
}
