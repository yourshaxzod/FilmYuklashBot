<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;

class Validator
{
    public static function isAdmin(Nutgram $bot): bool
    {
        global $adminIds;
        return in_array($bot->userId(), $adminIds);
    }

    public static function validateChannelUsername(string $username): bool
    {
        return preg_match('/^@[a-zA-Z0-9_]+$/', $username);
    }

    public static function validateVideoPartNumber(string $part): bool
    {
        return is_numeric($part) && intval($part) > 0;
    }
}
