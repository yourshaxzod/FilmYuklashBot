<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Helpers\{Menu};
use PDO;

class CommandHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        self::registerCommands($bot, $db);
    }

    public static function registerCommands(Nutgram $bot, PDO $db): void
    {
        $bot->onCommand('start', function (Nutgram $bot) {
            Menu::showMainMenu($bot);
        });

        $bot->onCommand('search', function (Nutgram $bot) {
            Menu::showSearchMenu($bot);
        });

        $bot->onCommand('favorites', function (Nutgram $bot) use ($db) {
            Menu::showFavoriteMenu($bot, $db);
        });

        $bot->onCommand('trending', function (Nutgram $bot) use ($db) {
            Menu::showTrendingMenu($bot, $db);
        });

        $bot->onCommand('categories', function (Nutgram $bot) use ($db) {
            Menu::showCategoriesMenu($bot, $db);
        });

        $bot->onCommand('recommendations', function (Nutgram $bot) use ($db) {
            Menu::showRecommendationsMenu($bot, $db);
        });
    }
}
