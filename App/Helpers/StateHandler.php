<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;

class StateHandler
{
    public static function handleCancel(Nutgram $bot): void
    {
        $message = $bot->message()->text;

        $bot->setUserData('state', null);
        $bot->setUserData('movie_data', null);
        $bot->setUserData('video_data', null);

        if ($message === "🚫 Bekor qilish") {
            Keyboard::showMainMenu($bot);
        } else {
            Keyboard::showMovieMenu($bot);
        }
    }

    public static function setMovieState(Nutgram $bot, string $state, array $data = []): void
    {
        $bot->setUserData('movie_data', $data);
        $bot->setUserData('state', $state);
    }

    public static function setVideoState(Nutgram $bot, string $state, array $data = []): void
    {
        $bot->setUserData('video_data', $data);
        $bot->setUserData('state', $state);
    }

    public static function clearState(Nutgram $bot): void
    {
        $bot->setUserData('state', null);
        $bot->setUserData('movie_data', null);
        $bot->setUserData('video_data', null);
    }
}
