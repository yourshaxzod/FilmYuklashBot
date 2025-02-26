<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use PDO;

class CallbackHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQuery(".*", function(Nutgram $bot){
            $bot->answerCallbackQuery(
                text: "Sooon...",
                show_alert: true
            );
        });
    }
}
