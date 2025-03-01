<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

class Validator
{
    public static function isAdmin(Nutgram $bot): bool
    {
        $adminIds = Config::getAdminIds();
        return in_array($bot->userId(), $adminIds, true);
    }

    public static function hasPermission(Nutgram $bot, string $permission): bool
    {
        if (self::isAdmin($bot)) {
            return true;
        }

        return false;
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

    public static function isVideo(object $file): bool
    {
        return isset($file->mime_type) && str_starts_with($file->mime_type, 'video/');
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

    public static function hasValidMessage(?Message $message): bool
    {
        return $message && ($message->text || $message->photo || $message->video || $message->document);
    }

    public static function isValidCallback(Nutgram $bot): bool
    {
        $callback = $bot->callbackQuery();
        return $callback && isset($callback->data);
    }

    public static function validatePage(int $page, int $totalPages): int
    {
        return max(1, min($page, max(1, $totalPages)));
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePhone(string $phone): bool
    {
        $phoneDigits = preg_replace('/\D/', '', $phone);
        return strlen($phoneDigits) >= 10;
    }

    public static function validateTextLength(string $text, int $minLength, int $maxLength): bool
    {
        $length = mb_strlen(trim($text), 'UTF-8');
        return $length >= $minLength && $length <= $maxLength;
    }
}
