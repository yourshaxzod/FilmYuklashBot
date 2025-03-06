<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Helpers\{Menu};
use PDO;

class CommandHandler
{
    /**
     * Register all command handlers
     */
    public static function register(Nutgram $bot, PDO $db): void
    {
        self::registerCommands($bot, $db);
    }

    /**
     * Register individual commands
     */
    private static function registerCommands(Nutgram $bot, PDO $db): void
    {
        // Start command - show main menu
        $bot->onCommand('start', function (Nutgram $bot) {
            Menu::showMainMenu($bot);
        });

        // Search command - show search menu
        $bot->onCommand('search', function (Nutgram $bot) {
            Menu::showSearchMenu($bot);
        });

        // Favorites command - show favorite movies
        $bot->onCommand('favorites', function (Nutgram $bot) use ($db) {
            Menu::showFavoriteMenu($bot, $db);
        });

        // Trending command - show trending movies
        $bot->onCommand('trending', function (Nutgram $bot) use ($db) {
            Menu::showTrendingMenu($bot, $db);
        });

        // Categories command - show categories
        $bot->onCommand('categories', function (Nutgram $bot) use ($db) {
            Menu::showCategoriesMenu($bot, $db);
        });

        // Recommendations command - show personalized recommendations
        $bot->onCommand('recommendations', function (Nutgram $bot) use ($db) {
            Menu::showRecommendationsMenu($bot, $db);
        });

        // Admin command - show admin panel
        $bot->onCommand('admin', function (Nutgram $bot) {
            if (\App\Helpers\Validator::isAdmin($bot)) {
                Menu::showAdminMenu($bot);
            } else {
                $bot->sendMessage(text: "⚠️ Bu buyruqdan foydalanish uchun admin huquqiga ega bo'lish kerak.");
            }
        });
    }
}