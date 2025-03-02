<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, StatisticsService, ChannelService, TavsiyaService, CategoryService};
use App\Helpers\{Button, Menu, State, Validator, Config, Keyboard};
use App\Models\{Movie, Video, Category, User};
use BcMath\Number;
use PDO;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class CommandHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onUpdate(function (Nutgram $bot) {
            print($bot->message()->text . " | " . State::getScreen($bot) . "\n" ?? "Habar yo'q");
        });
        self::registerCommands($bot, $db);
        self::registerUserButtons($bot, $db);
        self::registerAdmButtons($bot, $db);
    }

    public static function registerCommands(Nutgram $bot, PDO $db): void
    {
        $bot->onCommand('.*', function (Nutgram $bot) {
            State::clearAll($bot);
        });

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

    private static function registerUserButtons(Nutgram $bot, PDO $db): void
    {
        $bot->onUpdate(function (Nutgram $bot) {
            if ($bot->userId() === null || State::getScreen($bot) != State::MAIN) return;
        });

        $bot->onText(Button::SEARCH, function (Nutgram $bot) {
            Menu::showSearchMenu($bot);
        });

        $bot->onText(Button::FAVORITE, function (Nutgram $bot) use ($db) {
            Menu::showFavoriteMenu($bot, $db);
        });

        $bot->onText(Button::TRENDING, function (Nutgram $bot) use ($db) {
            Menu::showTrendingMenu($bot, $db);
        });

        $bot->onText(Button::CATEGORY, function (Nutgram $bot) use ($db) {
            Menu::showCategoriesMenu($bot, $db);
        });

        $bot->onText(Button::RECOMMENDATION, function (Nutgram $bot) use ($db) {
            Menu::showRecommendationsMenu($bot, $db);
        });
    }

    public static function registerAdmButtons(Nutgram $bot, PDO $db): void
    {
        $bot->onUpdate(function (Nutgram $bot) {
            if ($bot->userId() === null || !Validator::isAdmin($bot) || State::getScreen($bot) !== State::ADM_MAIN) return;
        });

        $bot->onText(Button::PANEL, function (Nutgram $bot) {
            Menu::showAdminMenu($bot);
        });

        $bot->onText(Button::MOVIE, function (Nutgram $bot) {
            Menu::showMovieManageMenu($bot);
        });

        $bot->onText(Button::CATEGORY, function (Nutgram $bot) use ($db) {
            Menu::showCategoryManageMenu($bot, $db);
        });

        $bot->onText(Button::CHANNEL, function (Nutgram $bot) use ($db) {
            Menu::showChannelManageMenu($bot, $db);
        });

        $bot->onText(Button::STATISTIC, function (Nutgram $bot) use ($db) {
            Menu::showChannelManageMenu($bot, $db);
        });

        $bot->onText(Button::MESSAGE, function (Nutgram $bot) {
            Menu::showBroadcastMenu($bot);
        });

        $bot->onText(Button::ADD_MOIVE, function (Nutgram $bot) {
            Menu::showAddMovieGuide($bot);
        });

        $bot->onText(Button::DEL_MOVIE, function (Nutgram $bot) {
            Menu::showDelMovieMenu($bot);
        });

        $bot->onText(Button::EDIT, function (Nutgram $bot) {
            Menu::showEditMovieGuide($bot);
        });

        $bot->onText(Button::LIST, function (Nutgram $bot) use ($db) {
            State::set($bot, 'state', 'movie_list_page');
            State::set($bot, 'movie_list_page', 1);

            MovieService::showMoviesList($bot, $db, 1);
        });

        $bot->onText(Button::ADD_CATEGORY, function (Nutgram $bot) {
            State::set($bot, 'state', 'add_category_name');
            Menu::showAddCategoryGuide($bot);
        });
    }
}
