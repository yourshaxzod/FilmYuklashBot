<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;

class State
{
    public static function getState(Nutgram $bot): ?string
    {
        $state = $bot->getUserData('state');

        if (!$state) {
            return null;
        }

        return $state;
    }

    public static function setState(Nutgram $bot, string $key, string $value): void
    {
        $bot->setUserData($key, $value);
    }

    public static function clearState(Nutgram $bot): void
    {
        $bot->setUserData('state', null);
    }
}
