<?php

declare(strict_types=1);

use App\Helpers\Config;

function connectDatabase(): PDO
{
    try {
        Config::load();

        $required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
        foreach ($required as $key) {
            if (empty(Config::get($key))) {
                throw new \PDOException("Bazaga ulanish uchun malumot yo'q: {$key}");
            }
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", Config::get('DB_HOST'), Config::get('DB_NAME'));

        $pdo = new PDO(
            $dsn,
            Config::get('DB_USER'),
            Config::get('DB_PASSWORD'),
            $options
        );

        $pdo->query("SELECT 1");

        return $pdo;
    } catch (\PDOException $e) {
        if (Config::isDebugMode()) {
            error_log("Bazaga ulanishda xatolik: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }

        throw new \PDOException("Ma'lumotlar bazasiga ulanishda xatolik yuz berdi.");
    }
}
