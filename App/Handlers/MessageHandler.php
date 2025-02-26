<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService};
use App\Helpers\{Menu, State};
use PDO;

class MessageHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onMessage(function (Nutgram $bot) use ($db) {
            $message = $bot->message();

            if (!$message->text) {
                return;
            }

            $state = State::getState($bot);
            if (!$state) {
                return;
            }

            if (self::handleMovieStates($bot, $db, $state, $message->text)) {
                return;
            }
        });
    }


    

    private static function handleMovieStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        switch ($state) {
            case 'search':
                if ($text === "↩️ Ortga qaytish") {
                    State::clearState($bot);
                    Menu::showMainMenu($bot);
                    break;
                }
                MovieService::search($bot, $db, $text);
                break;
            case 'panel':
                if ($text === "↩️ Ortga qaytish") {
                    State::clearState($bot);
                    Menu::showMainMenu($bot);
                    break;
                }
                break;
        }

        return false;
    }
}
