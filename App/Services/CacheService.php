<?php

namespace App\Services;

use PDO;
use Exception;
use App\Helpers\Config;

class CacheService
{
    private static string $cacheDir = __DIR__ . '/../../cache/';

    private static function getCacheFile(string $key)
    {
        return self::$cacheDir . md5($key) . '.json';
    }

    public static function get(string $key, mixed $default = null)
    {
        $cacheFile = self::getCacheFile($key);

        if (!file_exists($cacheFile)) {
            return $default;
        }

        $cacheTime = filemtime($cacheFile);
        if (time() - $cacheTime > Config::getCacheTTL()) {
            self::delete($key);
            return $default;
        }

        $cached = file_get_contents($cacheFile);
        return $cached ? json_decode($cached, true) : $default;
    }

    public static function set(string $key, mixed $data, ?int $ttl = null)
    {
        $cacheFile = self::getCacheFile($key);

        try {
            $cacheDir = dirname($cacheFile);
            if (!file_exists($cacheDir)) {
                mkdir($cacheDir, 0775, true);
            }

            file_put_contents($cacheFile, json_encode($data));

            if ($ttl !== null) {
                touch($cacheFile, time() + $ttl);
            }

            return true;
        } catch (Exception $e) {
            error_log("Keshni saqlashda xatolik: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(string $key)
    {
        $cacheFile = self::getCacheFile($key);

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return false;
    }

    public static function has(string $key)
    {
        $cacheFile = self::getCacheFile($key);

        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($cacheFile);
        return time() - $cacheTime <= Config::getCacheTTL();
    }

    public static function clearAllCache()
    {
        $count = 0;

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

    public static function clearExpiredCache()
    {
        $count = 0;

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

    public static function clearSectionCache(string $section)
    {
        $count = 0;

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
}
