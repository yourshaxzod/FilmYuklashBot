<?php

namespace App\Helpers;

class Formatter
{
    public static function errorMessage(string $error): string
    {
        return "🚫: {$error}";
    }

    public static function successMessage(string $message): string
    {
        return "✅: {$message}";
    }

    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function formatNumber($number)
    {
        if ($number < 1000) {
            return number_format($number);
        } elseif ($number < 1000000) {
            $formatted = $number / 1000;
            return round($formatted, 1) . 'K';
        } elseif ($number < 1000000000) {
            $formatted = $number / 1000000;
            return round($formatted, 1) . 'M';
        } else {
            $formatted = $number / 1000000000;
            return round($formatted, 1) . 'B';
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

    public static function isNumericString($str)
    {
        return ctype_digit($str);
    }

    public static function stringToInteger($str)
    {
        if (self::isNumericString($str)) {
            return intval($str);
        }
        return false;
    }
}
