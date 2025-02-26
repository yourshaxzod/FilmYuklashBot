<?php

namespace App\Helpers;

use App\Models\Movie;
use PDO;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class Menu
{
    public static function showMainMenu(Nutgram $bot): void
    {
        self::sendMenu($bot, Text::MainMenu(), Keyboard::MainMenu($bot));
    }

    public static function showAdminMenu(Nutgram $bot): void
    {
        if (!Validator::isAdmin($bot)) {
            return;
        }
        State::setState($bot, 'state', 'panel');
        self::sendMenu($bot, Text::AdminMenu(), Keyboard::AdminMenu());
    }

    public static function showSearchMenu(Nutgram $bot): void
    {
        State::setState($bot, 'state', 'search');
        self::sendMenu($bot, Text::SearchMenu(), Keyboard::BackMain());
    }

    public static function showFavoriteMenu(Nutgram $bot, PDO $db): void
    {
        $movies = Movie::getLikedMovies($db, $bot->userId());
        if (count($movies) <= 0) {
            self::sendMenu($bot, "Sevimli kinolar yo'q");
        } else {
            self::sendMenu($bot, "Kinolar:", Keyboard::MovieList($movies));
        }
    }

    public static function showTrendingMenu(Nutgram $bot, PDO $db): void
    {
        $trending = Movie::getTrending($db);
        if (count($trending) <= 0) {
            self::sendMenu($bot, "Trending bo'sh");
        } else {
            self::sendMenu($bot, "trending videolar", Keyboard::MovieList($trending));
        }
    }

    public static function showMovieManageMenu(Nutgram $bot): void
    {
        if (!Validator::isAdmin($bot)) {
            self::showError($bot, "Kechirasiz, siz admin emassiz!");
            return;
        }
        self::sendMenu($bot, Text::MovieManage(), Keyboard::MovieManageMenu());
    }

    public static function showAddMovieGuide(Nutgram $bot): void
    {
        self::sendMenu($bot, Text::AddMovie(), Keyboard::Cancel());
    }

    public static function showEditMovieGuide(Nutgram $bot): void
    {
        self::sendMenu($bot, Text::EditMovie(), Keyboard::MovieManageMenu());
    }

    public static function showAddVideoGuide(Nutgram $bot, array $movie): void
    {
        self::sendMenu($bot, Text::AddVideo($movie), Keyboard::Cancel());
    }

    public static function showAddChannelGuide(Nutgram $bot): void
    {
        self::sendMenu($bot, Text::AddChannel(), Keyboard::Cancel());
    }

    public static function showError(Nutgram $bot, string $message): void
    {
        $text = Formatter::errorMessage($message);
        self::sendMenu($bot, $text, Keyboard::MainMenu($bot));
    }

    public static function showSuccess(Nutgram $bot, string $message): void
    {
        $text = Formatter::successMessage($message);
        self::sendMenu($bot, $text, Keyboard::MainMenu($bot));
    }

    private static function sendMenu(Nutgram $bot, string $text, $keyboard = null): void
    {
        try {
            $bot->sendMessage(
                text: $text,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Throwable $e) {
            if (Config::isDebugMode()) {
                error_log("Menu send error: " . $e->getMessage());
            }

            try {
                $bot->sendMessage(
                    text: strip_tags($text),
                    reply_markup: $keyboard
                );
            } catch (\Throwable $e) {
                error_log("Menu send critical error: " . $e->getMessage());
            }
        }
    }

    public static function updateMenu(Nutgram $bot, string $text, $keyboard = null): void
    {
        try {
            $bot->editMessageText(
                text: $text,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Throwable $e) {
            if (Config::isDebugMode()) {
                error_log("Menu update error: " . $e->getMessage());
            }
        }
    }
}
