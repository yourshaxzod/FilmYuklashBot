<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, StatisticsService, ChannelService};
use App\Helpers\{Menu, State, Validator};
use App\Models\{Movie, Video};
use PDO;

class CommandHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onCommand('.*', function (Nutgram $bot) {
            State::clearState($bot);
        });

        $bot->onCommand('start', function (Nutgram $bot) use ($db) {
            self::registerUser($bot, $db);

            if (!ChannelService::showSubscriptionRequirement($bot, $db)) {
                return;
            }

            Menu::showMainMenu($bot);
        });

        $bot->onCommand('search', function (Nutgram $bot) use ($db) {
            if (!ChannelService::showSubscriptionRequirement($bot, $db)) {
                return;
            }

            Menu::showSearchMenu($bot);
        });

        $bot->onCommand('favorites', function (Nutgram $bot) use ($db) {
            if (!ChannelService::showSubscriptionRequirement($bot, $db)) {
                return;
            }

            MovieService::showFavorites($bot, $db);
        });

        $bot->onCommand('trending', function (Nutgram $bot) use ($db) {
            if (!ChannelService::showSubscriptionRequirement($bot, $db)) {
                return;
            }

            MovieService::showTrending($bot, $db);
        });

        self::registerAdminCommands($bot, $db);

        self::registerMenuButtons($bot, $db);
    }

    private static function registerMenuButtons(Nutgram $bot, PDO $db): void
    {
        $bot->onText('🔍 Qidirish', function (Nutgram $bot) use ($db) {
            if (!ChannelService::showSubscriptionRequirement($bot, $db)) {
                return;
            }

            Menu::showSearchMenu($bot);
        });

        $bot->onText('❤️ Sevimlilar', function (Nutgram $bot) use ($db) {
            if (!ChannelService::showSubscriptionRequirement($bot, $db)) {
                return;
            }

            MovieService::showFavorites($bot, $db);
        });

        $bot->onText('🔥 Trendlar', function (Nutgram $bot) use ($db) {
            if (!ChannelService::showSubscriptionRequirement($bot, $db)) {
                return;
            }

            MovieService::showTrending($bot, $db);
        });

        $bot->onText('↩️ Ortga qaytish', function (Nutgram $bot) {
            State::clearState($bot);
            Menu::showMainMenu($bot);
        });

        $bot->onText('🚫 Bekor qilish', function (Nutgram $bot) {
            State::clearState($bot);
            Menu::showMainMenu($bot);
        });
    }

    private static function registerAdminCommands(Nutgram $bot): void
    {
        $bot->onText("🛠 Statistika va Boshqaruv", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage("⚠️ Sizda admin huquqlari yo'q!");
                return;
            }

            Menu::showAdminMenu($bot);
        });

        $bot->onText("🎬 Kinolar", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            Menu::showMovieManageMenu($bot);
        });

        $bot->onText("➕ Kino qo'shish", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::setState($bot, 'state', 'add_movie_title');
            Menu::showAddMovieGuide($bot);
        });

        $bot->onText("✏️ Tahrirlash", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::setState($bot, 'state', 'edit_movie_id');
            $bot->sendMessage(
                text: "✏️ <b>Kinoni tahrirlash</b>\n\n" .
                    "Tahrirlash uchun kino ID raqamini kiriting:",
                parse_mode: 'HTML'
            );
        });

        $bot->onText("➖ Kino o'chirish", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            State::setState($bot, 'state', 'delete_movie_id');
            $bot->sendMessage(
                text: "🗑 <b>Kinoni o'chirish</b>\n\n" .
                    "O'chirish uchun kino ID raqamini kiriting:",
                parse_mode: 'HTML'
            );
        });

        $bot->onText("◀️ Admin panelga qaytish", function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            Menu::showAdminMenu($bot);
        });
    }

    private static function registerUser(Nutgram $bot, PDO $db): void
    {
        try {
            $userId = $bot->userId();
            $user = $bot->user();

            if (!$userId || !$user) {
                return;
            }

            $stmt = $db->prepare("SELECT id FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([$userId]);
                return;
            }

            $stmt = $db->prepare("
                INSERT INTO users (user_id, status, created_at) 
                VALUES (?, 'active', NOW())
            ");
            $stmt->execute([
                $userId,
            ]);
        } catch (\Exception $e) {
            if (class_exists('App\Helpers\Config') && method_exists('App\Helpers\Config', 'isDebugMode') && \App\Helpers\Config::isDebugMode()) {
                error_log("User registration error: " . $e->getMessage());
            }
        }
    }
}
