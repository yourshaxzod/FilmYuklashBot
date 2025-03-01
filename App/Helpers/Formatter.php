<?php

namespace App\Helpers;

class Formatter
{
    public static function errorMessage(string $error): string
    {
        return "🚫 {$error}";
    }

    public static function successMessage(string $message): string
    {
        return "✅ {$message}";
    }

    public static function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function formatNumber($number, int $precision = 1): string
    {
        if ($number < 1000) {
            return number_format($number);
        } elseif ($number < 1000000) {
            $formatted = $number / 1000;
            return round($formatted, $precision) . 'K';
        } elseif ($number < 1000000000) {
            $formatted = $number / 1000000;
            return round($formatted, $precision) . 'M';
        } else {
            $formatted = $number / 1000000000;
            return round($formatted, $precision) . 'B';
        }
    }

    public static function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}s";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($seconds > 0 || empty($parts)) {
            $parts[] = "{$seconds}s";
        }

        return implode(' ', $parts);
    }

    public static function formatDate(string $date): string
    {
        return date('d.m.Y', strtotime($date));
    }

    public static function formatDateTime(string $datetime): string
    {
        return date('d.m.Y H:i', strtotime($datetime));
    }

    public static function truncateText(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    public static function escapeHTML(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    public static function isNumericString($str): bool
    {
        if ($str === null || $str === '') {
            return false;
        }
        return ctype_digit((string)$str);
    }

    public static function stringToInteger($str)
    {
        if (self::isNumericString($str)) {
            return intval($str);
        }
        return false;
    }

    public static function formatRating(float $rating): string
    {
        $rating = min(5, max(0, $rating));
        $fullStars = floor($rating);
        $halfStar = round($rating - $fullStars, 1) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

        $stars = str_repeat('⭐️', $fullStars);
        $stars .= $halfStar ? '✨' : '';
        $stars .= str_repeat('☆', $emptyStars);

        return $stars . " ({$rating})";
    }

    public static function formatCategories(array $categories): string
    {
        if (empty($categories)) {
            return "Kategoriya belgilanmagan";
        }

        $categoryNames = array_column($categories, 'name');
        return '#' . implode(' #', $categoryNames);
    }
}
