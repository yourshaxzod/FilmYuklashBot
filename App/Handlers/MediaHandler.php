<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use PDO;

class MediaHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onPhoto(function (Nutgram $bot) use ($db) {
            $bot->sendMessage("Photo handled!");
        });

        $bot->onVideo(function (Nutgram $bot) use ($db) {
            $bot->sendMessage("Video hanled!");
        });
    }
}
