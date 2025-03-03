<?php

namespace App\Services;

use PDO;
use Exception;
use App\Helpers\Config;

/**
 * Kesh ma'lumotlari bilan ishlash uchun xizmat klassi
 */
class CacheService
{
    /**
     * Kesh fayllari jildi
     * 
     * @var string
     */
    private static string $cacheDir = __DIR__ . '/../../cache/';

    /**
     * Kesh faylini olish
     * 
     * @param string $key Kesh kaliti
     * @return string Kesh fayl yo'li
     */
    private static function getCacheFile(string $key): string
    {
        return self::$cacheDir . md5($key) . '.json';
    }

    /**
     * Kesh ma'lumotini olish
     * 
     * @param string $key Kesh kaliti
     * @param mixed $default Standart qiymat
     * @return mixed Kesh ma'lumoti yoki $default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheFile = self::getCacheFile($key);

        // Kesh fayli mavjud emas
        if (!file_exists($cacheFile)) {
            return $default;
        }

        // Kesh vaqti o'tgan
        $cacheTime = filemtime($cacheFile);
        if (time() - $cacheTime > Config::getCacheTTL()) {
            self::delete($key);
            return $default;
        }

        // Keshni o'qish
        $cached = file_get_contents($cacheFile);
        return $cached ? json_decode($cached, true) : $default;
    }

    /**
     * Kesh ma'lumotini saqlash
     * 
     * @param string $key Kesh kaliti
     * @param mixed $data Saqlanadigan ma'lumot
     * @param int|null $ttl Saqlash muddati (null bo'lsa, konfiguratsiyadan olinadi)
     * @return bool Saqlash muvaffaqiyati
     */
    public static function set(string $key, mixed $data, ?int $ttl = null): bool
    {
        $cacheFile = self::getCacheFile($key);

        try {
            // Kesh jildini yaratish
            $cacheDir = dirname($cacheFile);
            if (!file_exists($cacheDir)) {
                mkdir($cacheDir, 0775, true);
            }

            // Ma'lumotni saqlash
            file_put_contents($cacheFile, json_encode($data));

            // TTL o'rnatish (touch bilan)
            if ($ttl !== null) {
                touch($cacheFile, time() + $ttl);
            }

            return true;
        } catch (Exception $e) {
            error_log("Keshni saqlashda xatolik: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kesh ma'lumotini o'chirish
     * 
     * @param string $key Kesh kaliti
     * @return bool O'chirish muvaffaqiyati
     */
    public static function delete(string $key): bool
    {
        $cacheFile = self::getCacheFile($key);

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return false;
    }

    /**
     * Kesh mavjudligini tekshirish
     * 
     * @param string $key Kesh kaliti
     * @return bool Kesh mavjudligi
     */
    public static function has(string $key): bool
    {
        $cacheFile = self::getCacheFile($key);

        // Kesh fayli mavjud
        if (!file_exists($cacheFile)) {
            return false;
        }

        // Kesh vaqti o'tgan emas
        $cacheTime = filemtime($cacheFile);
        return time() - $cacheTime <= Config::getCacheTTL();
    }

    /**
     * Barcha keshlarni tozalash
     * 
     * @return int Tozalangan keshlar soni
     */
    public static function clearAllCache(): int
    {
        $count = 0;

        // Kesh jildi mavjud emas
        if (!file_exists(self::$cacheDir)) {
            return $count;
        }

        $files = glob(self::$cacheDir . '*.json');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Muddati o'tgan keshlarni tozalash
     * 
     * @return int Tozalangan keshlar soni
     */
    public static function clearExpiredCache(): int
    {
        $count = 0;

        // Kesh jildi mavjud emas
        if (!file_exists(self::$cacheDir)) {
            return $count;
        }

        $files = glob(self::$cacheDir . '*.json');

        foreach ($files as $file) {
            if (is_file($file)) {
                $cacheTime = filemtime($file);
                if (time() - $cacheTime > Config::getCacheTTL()) {
                    unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Bo'lim bo'yicha keshlarni tozalash
     * 
     * @param string $section Bo'lim nomi
     * @return int Tozalangan keshlar soni
     */
    public static function clearSectionCache(string $section): int
    {
        $count = 0;

        // Kesh jildi mavjud emas
        if (!file_exists(self::$cacheDir)) {
            return $count;
        }

        $files = glob(self::$cacheDir . md5($section . '*') . '.json');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Redisni tekshirish funksiyasi (agar kerak bo'lsa)
     * 
     * @return bool Redis mavjudligi
     */
    public static function hasRedis(): bool
    {
        return class_exists('Redis') && extension_loaded('redis');
    }

    /**
     * Statistika ma'lumotlarini keshdan olish
     * 
     * @param PDO $db Database ulanish
     * @param bool $refresh Majburiy yangilash
     * @return array Statistika ma'lumotlari
     */
    public static function getStats(PDO $db, bool $refresh = false): array
    {
        $key = 'stats';

        // Agar yangilash kerak bo'lmasa va kesh mavjud bo'lsa
        if (!$refresh && self::has($key)) {
            return self::get($key, []);
        }

        try {
            // Statistika ma'lumotlarini bazadan olish
            $stats = StatisticsService::collectStats($db);

            // Keshga saqlash
            self::set($key, $stats);

            return $stats;
        } catch (Exception $e) {
            error_log("Statistika ma'lumotlarini olishda xatolik: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Trend kinolarni keshdan olish
     * 
     * @param PDO $db Database ulanish
     * @param int $limit Kinolar soni
     * @param bool $refresh Majburiy yangilash
     * @return array Trend kinolar
     */
    public static function getTrending(PDO $db, int $limit = 10, bool $refresh = false): array
    {
        $key = 'trending_' . $limit;

        // Agar yangilash kerak bo'lmasa va kesh mavjud bo'lsa
        if (!$refresh && self::has($key)) {
            return self::get($key, []);
        }

        try {
            // Trend kinolarni bazadan olish
            $trending = MovieService::getTrendingForCache($db, $limit);

            // Keshga saqlash
            self::set($key, $trending);

            return $trending;
        } catch (Exception $e) {
            error_log("Trend kinolarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Kategoriyalarni keshdan olish
     * 
     * @param PDO $db Database ulanish
     * @param bool $refresh Majburiy yangilash
     * @return array Kategoriyalar
     */
    public static function getCategories(PDO $db, bool $refresh = false): array
    {
        $key = 'categories';

        // Agar yangilash kerak bo'lmasa va kesh mavjud bo'lsa
        if (!$refresh && self::has($key)) {
            return self::get($key, []);
        }

        try {
            // Kategoriyalarni bazadan olish
            $categories = CategoryService::getAllForCache($db);

            // Keshga saqlash
            self::set($key, $categories);

            return $categories;
        } catch (Exception $e) {
            error_log("Kategoriyalarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }
}
