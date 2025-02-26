<?php

declare(strict_types=1);

namespace App\Helpers;

use Dotenv\Dotenv;

class Config
{
    private static bool $loaded = false;
    private static array $cache = [];

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $dotenv->required([
            'BOT_TOKEN',
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'ADMIN_IDS'
        ]);

        self::$cache = [
            'ITEMS_PER_PAGE' => (int)($_ENV['ITEMS_PER_PAGE'] ?? 10),
            'DEBUG_MODE' => (bool)($_ENV['DEBUG_MODE'] ?? false),
            'CACHE_TTL' => (int)($_ENV['CACHE_TTL'] ?? 3600),
        ];

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$cache[$key] ?? $_ENV[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$cache[$key] = $value;
    }

    public static function getAdminIds(): array
    {
        $ids = self::get('ADMIN_IDS', '');
        return array_map('intval', array_filter(explode(',', $ids)));
    }

    public static function getItemsPerPage(): int
    {
        return self::get('ITEMS_PER_PAGE', 10);
    }

    public static function isDebugMode(): bool
    {
        return self::get('DEBUG_MODE', false);
    }

    public static function getCacheTTL(): int
    {
        return self::get('CACHE_TTL', 3600);
    }
}
