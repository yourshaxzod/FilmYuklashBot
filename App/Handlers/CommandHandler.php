<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, StatisticsService, ChannelService, TavsiyaService, CategoryService};
use App\Helpers\{Menu, State, Validator, Config, Keyboard};
use App\Models\{Movie, Video, Category, User};
use PDO;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
class CommandHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onCommand('.*', function (Nutgram $bot) {
            State::clearAll($bot);
        });

        $bot->onCommand('start', function (Nutgram $bot) use ($db) {
            $user = User::register($db, $bot);

            if ($user && $user['status'] === 'blocked') {
                $bot->sendMessage("⚠️ Kechirasiz, sizning hisobingiz bloklangan. Admin bilan bog'laning.");
                return;
            }

            Menu::showMainMenu($bot);
        });

        $bot->onCommand('search', function (Nutgram $bot) use ($db) {
            Menu::showSearchMenu($bot);

            State::set($bot, 'state', 'search');
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

        self::registerMenuButtons($bot, $db);
    }

    private static function registerMenuButtons(Nutgram $bot, PDO $db): void
    {
        $bot->onText('🔍 Qidirish', function (Nutgram $bot) use ($db) {
            Menu::showSearchMenu($bot);

            State::set($bot, 'state', 'search');
        });

        $bot->onText('❤️ Sevimlilar', function (Nutgram $bot) use ($db) {
            Menu::showFavoriteMenu($bot, $db);
        });

        $bot->onText('🔥 Trendlar', function (Nutgram $bot) use ($db) {
            Menu::showTrendingMenu($bot, $db);
        });

        $bot->onText('🎭 Janrlar', function (Nutgram $bot) use ($db) {
            Menu::showCategoriesMenu($bot, $db);
        });

        $bot->onText('⭐️ Tavsiyalar', function (Nutgram $bot) use ($db) {
            Menu::showRecommendationsMenu($bot, $db);
        });

        $bot->onText('🛠 Admin panel', function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage("⚠️ Sizda admin huquqlari yo'q!");
                return;
            }

            Menu::showAdminMenu($bot);
        });

        $bot->onText('↩️ Orqaga', function (Nutgram $bot) {
            State::clearAll($bot);
            Menu::showMainMenu($bot);
        });

        $bot->onText('🚫 Bekor qilish', function (Nutgram $bot) {
            State::clearAll($bot);
            Menu::showMainMenu($bot);
        });

        $bot->onText('◀️ Admin panelga qaytish', function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage("⚠️ Sizda admin huquqlari yo'q!");
                return;
            }

            State::clearAll($bot);
            Menu::showAdminMenu($bot);
        });

        $bot->onText('🎬 Kinolar', function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            Menu::showMovieManageMenu($bot);
        });

        $bot->onText('🏷 Kategoriyalar', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            CategoryService::showCategoryList($bot, $db, true);
        });

        $bot->onText('🔐 Kanallar', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            ChannelService::showChannels($bot, $db);
        });

        $bot->onText('📊 Statistika', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            StatisticsService::showStats($bot, $db);
        });

        $bot->onText('📬 Xabarlar', function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::set($bot, 'state', 'broadcast_message');
            $bot->sendMessage(
                text: "📣 <b>Foydalanuvchilarga xabar yuborish</b>\n\nYubormoqchi bo'lgan xabarni kiriting:", 
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::cancel()
            );
        });

        $bot->onText("➕ Kino qo'shish", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::setState($bot, 'add_movie_title');
            Menu::showAddMovieGuide($bot);
        });

        $bot->onText("➖ Kino o'chirish", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::set($bot, 'state', 'delete_movie_id');
            $bot->sendMessage(
                text: "🗑 <b>Kinoni o'chirish</b>\n\nO'chirish uchun kino ID raqamini kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::back()
            );
        });

        $bot->onText("✏️ Tahrirlash", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::set($bot, 'state', 'edit_movie_id');
            $bot->sendMessage(
                text: "✏️ <b>Kinoni tahrirlash</b>\n\nTahrirlash uchun kino ID raqamini kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::back()
            );
        });

        $bot->onText("📋 Ro'yxat", function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::set($bot, 'state', 'movie_list_page');
            State::set($bot, 'movie_list_page', 1);

            MovieService::showMoviesList($bot, $db, 1);
        });

        $bot->onText("➕ Kategoriya qo'shish", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::set($bot, 'state', 'add_category_name');
            Menu::showAddCategoryGuide($bot);
        });
    }
}