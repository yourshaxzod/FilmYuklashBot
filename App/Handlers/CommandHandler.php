<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService};
use App\Helpers\{Menu, State};
use PDO;

class CommandHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onCommand('.*', function (Nutgram $bot) {
            State::clearState($bot);
        });

        $bot->onCommand('start', function (Nutgram $bot) use ($db) {
            Menu::showMainMenu($bot);
        });

        self::registerMenuButtons($bot, $db);
        self::registerAdminButtons($bot);
    }

    private static function registerMenuButtons(Nutgram $bot, PDO $db): void
    {
        $bot->onText('🔍 Qidirish', function (Nutgram $bot) use ($db) {
            Menu::showSearchMenu($bot);
        });

        $bot->onText('❤️ Sevimlilar', function (Nutgram $bot) use ($db) {
            MovieService::showFavorites($bot, $db);
        });

        $bot->onText('🔥 Trendlar', function (Nutgram $bot) use ($db) {
            MovieService::showTrending($bot, $db);
        });
    }

    private static function registerAdminButtons(Nutgram $bot): void
    {
        $bot->onText("🛠 Statistika va Boshqaruv", function (Nutgram $bot) {
            Menu::showAdminMenu($bot);
        });
    }
}
