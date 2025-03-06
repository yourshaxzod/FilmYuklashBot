<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;

class Validator
{
    public static function isAdmin(Nutgram $bot): bool
    {
        $adminIds = Config::getAdminIds();
        return in_array($bot->userId(), $adminIds, true);
    }

    public static function validateMovieTitle(string $title): bool
    {
        $title = trim($title);
        $length = mb_strlen($title, 'UTF-8');
        return $length >= 2 && $length <= 255;
    }

    public static function validateMovieDescription(string $description): bool
    {
        $description = trim($description);
        $length = mb_strlen($description, 'UTF-8');
        return $length >= 10 && $length <= 4000;
    }

    public static function validateMovieYear(int $year): bool
    {
        $currentYear = (int)date('Y');
        return $year >= 1900 && $year <= ($currentYear + 1);
    }

    public static function validateVideoTitle(string $title): bool
    {
        $title = trim($title);
        $length = mb_strlen($title, 'UTF-8');
        return $length >= 2 && $length <= 255;
    }

    public static function validateVideoPartNumber(string|int $part): bool
    {
        $part = (int)$part;
        return $part > 0 && $part <= 1000;
    }

    public static function validateCategoryName(string $name): bool
    {
        $name = trim($name);
        $length = mb_strlen($name, 'UTF-8');
        return $length >= 2 && $length <= 100;
    }

    public static function validateCategorySlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $slug) && strlen($slug) <= 100;
    }

    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateNumericInput(string|int $input, ?int $min = null, ?int $max = null): bool
    {
        if (!is_numeric($input)) {
            return false;
        }

        $number = (int)$input;

        if ($min !== null && $number < $min) {
            return false;
        }

        if ($max !== null && $number > $max) {
            return false;
        }

        return true;
    }
}
