<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Category};
use PDO;

class Menu
{
    public static function showMainMenu(Nutgram $bot): void
    {
        State::clearAll($bot);
        State::setScreen($bot, State::MAIN);
        self::sendMenu($bot, Text::mainMenu(), Keyboard::mainMenu($bot));
    }

    public static function showAdminMenu(Nutgram $bot): void
    {
        State::clearAll($bot);
        State::setScreen($bot, State::ADM_MAIN);
        self::sendMenu($bot, Text::adminMenu(), Keyboard::adminMenu());
    }

    public static function showSearchMenu(Nutgram $bot): void
    {
        State::setScreen($bot, State::MAIN);
        State::setState($bot, State::SEARCH);
        self::sendMenu($bot, Text::searchMenu(), Keyboard::back());
    }

    public static function showFavoriteMenu(Nutgram $bot, PDO $db): void
    {
        $userId = $bot->userId();
        $movies = Movie::getLikedMovies($db, $userId, Config::getMaxLikesPerUser());

        if (empty($movies)) {
            self::sendMenu($bot, "😔 <b>Sizda hali sevimli kinolar yo'q.</b>");
            return;
        }

        $message = "❤️ <b>Sevimli kinolaringiz</b>\n\n";
        $message .= "Jami: " . count($movies) . " ta kino\n";
        $message .= "Kerakli kinoni tanlang:";

        $buttons = [];
        foreach ($movies as $movie) {
            $buttons[] = [
                Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")
            ];
        }

        $keyboard = Keyboard::getInlineKeyboard($buttons);
        self::sendMenu($bot, $message, $keyboard);
    }

    public static function showTrendingMenu(Nutgram $bot, PDO $db): void
    {
        $trending = Movie::getTrendingMovies($db, Config::get('ITEMS_PER_PAGE'), $bot->userId());

        if (empty($trending)) {
            self::sendMenu($bot, "😔 <b>Hozircha trend kinolar yo'q.</b>");
            return;
        }

        $message = "🔥 <b>Trend kinolar</b>\n\n";
        $message .= "Eng ko'p ko'rilgan va yoqtirilgan kinolar:";

        $buttons = [];
        foreach ($trending as $movie) {
            $buttons[] = [
                Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")
            ];
        }

        $keyboard = Keyboard::getInlineKeyboard($buttons);
        self::sendMenu($bot, $message, $keyboard);
    }

    public static function showCategoriesMenu(Nutgram $bot, PDO $db): void
    {
        $categories = Category::getAll($db);

        if (empty($categories)) {
            self::sendMenu($bot, "😔 <b>Hozircha janrlar yo'q.</b>");
            return;
        }

        $message = "🎭 <b>Janrlar</b>\n\n";
        $message .= "Quyidagi janrlardan birini tanlang:";

        $keyboard = Keyboard::categoryList($categories);
        self::sendMenu($bot, $message, $keyboard);
    }

    public static function showRecommendationsMenu(Nutgram $bot, PDO $db): void
    {
        $userId = $bot->userId();
        $recommendations = Movie::getRecommendations($db, $userId, Config::getRecommendationCount());

        if (empty($recommendations)) {
            $message = "⭐️ <b>Tavsiyalar</b>\n\n";
            $message .= "Hozircha sizga tavsiyalar yo'q. Ko'proq kinolarni ko'ring va yoqtiring.";
            self::sendMenu($bot, $message);
            return;
        }

        $message = "⭐️ <b>Tavsiyalar</b>\n\n";
        $message .= "Sizning qiziqishlaringiz asosida tanlab olingan kinolar:";

        $buttons = [];
        foreach ($recommendations as $movie) {
            $score = isset($movie['recommendation_score']) ?
                " (" . number_format($movie['recommendation_score'], 1) . "⭐️)" : "";
            $buttons[] = [
                Keyboard::getCallbackButton(
                    "🎬 {$movie['title']}{$score}",
                    "movie_{$movie['id']}"
                )
            ];
        }

        $keyboard = Keyboard::getInlineKeyboard($buttons);
        self::sendMenu($bot, $message, $keyboard);
    }

    public static function showMovieManageMenu(Nutgram $bot): void
    {
        State::clear($bot, ['state']);
        State::setScreen($bot, State::ADM_MOVIE);
        self::sendMenu($bot, Text::movieManage(), Keyboard::movieManageMenu());
    }

    public static function showCategoryManageMenu(Nutgram $bot): void
    {
        State::setScreen($bot, State::ADM_CATEGORY);
        self::sendMenu($bot, Text::categoryManage(), Keyboard::categoryManageMenu());
    }

    public static function showBroadcastMenu(Nutgram $bot): void
    {
        State::setState($bot, 'broadcast_message');
        self::sendMenu($bot, Text::broadcastMessage(), Keyboard::cancel());
    }

    public static function showAddMovieGuide(Nutgram $bot): void
    {
        State::setState($bot, 'add_movie_title');
        self::sendMenu($bot, Text::addMovie(), Keyboard::cancel());
    }


    public static function showAddCategoryGuide(Nutgram $bot): void
    {
        self::sendMenu($bot, Text::addCategory(), Keyboard::cancel());
    }

    public static function showError(Nutgram $bot, string $message): void
    {
        $text = Formatter::errorMessage($message);
        self::sendMenu($bot, $text, Keyboard::mainMenu($bot));
    }

    public static function showSuccess(Nutgram $bot, string $message): void
    {
        $text = Formatter::successMessage($message);
        self::sendMenu($bot, $text, Keyboard::mainMenu($bot));
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

            try {
                self::sendMenu($bot, $text, $keyboard);
            } catch (\Throwable $e) {
                error_log("Menu update fallback error: " . $e->getMessage());
            }
        }
    }
}
