<?php

namespace App\Helpers;

use App\Models\{Movie, Category};
use App\Services\CategoryService;
use App\Services\ChannelService;
use App\Services\StatisticsService;
use PDO;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class Menu
{
    // MAIN MENU
    public static function showMainMenu(Nutgram $bot): void
    {
        State::clearAll($bot);
        State::setScreen($bot, State::MAIN);
        self::sendMenu($bot, Text::mainMenu(), Keyboard::mainMenu($bot));
    }

    // ADM MENU
    public static function showAdminMenu(Nutgram $bot): void
    {
        State::clearAll($bot);
        State::setScreen($bot, State::ADM_MAIN);
        self::sendMenu($bot, Text::adminMenu(), Keyboard::adminMenu());
    }

    // SEARCH MENU
    public static function showSearchMenu(Nutgram $bot): void
    {
        State::setScreen($bot, State::MAIN);
        State::setState($bot, State::SEARCH);
        self::sendMenu($bot, Text::searchMenu(), Keyboard::back());
    }

    // FAVORITE
    public static function showFavoriteMenu(Nutgram $bot, PDO $db, int $page = 1): void
    {
        $userId = $bot->userId();
        $perPage = Config::getItemsPerPage();
        $offset = ($page - 1) * $perPage;

        $movies = Movie::getLikedMovies($db, $userId, $perPage, $offset);
        $totalLiked = count($movies);
        $totalPages = ceil($totalLiked / $perPage);

        if (count($movies) <= 0) {
            self::sendMenu($bot, "😔 <b>Sizda hali sevimli kinolar yo'q.</b>");
            return;
        }

        $totalPages = ceil($totalLiked / $perPage);
        $message = "❤️ <b>Sevimli kinolaringiz {$totalLiked} ta.</b>\n\n";

        $movieButtons = [];
        foreach ($movies as $movie) {
            $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")];
        }

        $keyboard = Keyboard::pagination('favorites', $page, $totalPages, $movieButtons);

        self::sendMenu($bot, $message, $keyboard);
    }

    // TRENDING
    public static function showTrendingMenu(Nutgram $bot, PDO $db): void
    {
        $perPage = Config::getItemsPerPage();

        $trending = Movie::getTrendingMovies($db, $perPage, $bot->userId(), 0);
        $totalMovies = Movie::getCountMovies($db);

        if (count($trending) <= 0) {
            self::sendMenu($bot, "😔 <b>Hozircha trend kinolar yo'q.</b>");
            return;
        }

        $message = "🔥 <b>Trend kinolar</b>\n\n";

        $movieButtons = [];
        foreach ($trending as $movie) {
            $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")];
        }

        self::sendMenu($bot, $message);
    }

    public static function showCategoriesMenu(Nutgram $bot, PDO $db): void
    {
        $categories = Category::getAll($db);

        if (count($categories) <= 0) {
            self::sendMenu($bot, "😔 <b>Hozircha janrlar yo'q.</b>");
            return;
        }

        $message = "🏷 <b>Kategoriyalar</b>\n\n";
        $message .= "Quyidagi kategoriyalardan birini tanlang:";

        $keyboard = Keyboard::categoryList($categories);

        self::sendMenu($bot, $message, $keyboard);
    }

    public static function showCategoryMoviesMenu(Nutgram $bot, PDO $db, int $categoryId, int $page = 1): void
    {
        $category = Category::findById($db, $categoryId);

        if (!$category) {
            self::showError($bot, Text::categoryNotFound());
            return;
        }

        $perPage = Config::getItemsPerPage();
        $offset = ($page - 1) * $perPage;

        $movies = Movie::getByCategoryId($db, $categoryId, $bot->userId(), $perPage, $offset);
        $totalMovies = Movie::getCountByCategoryId($db, $categoryId);

        if (count($movies) <= 0) {
            $message = "🏷 <b>Kategoriya:</b> {$category['name']}\n\n";
            $message .= "Bu kategoriyada hozircha kinolar yo'q.";

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: "🔙 Kategoriyalarga qaytish",
                        callback_data: "categories"
                    )
                );

            self::sendMenu($bot, $message, $keyboard);
            return;
        }

        $totalPages = ceil($totalMovies / $perPage);
        $message = "🏷 <b>Kategoriya:</b> {$category['name']} (sahifa {$page}/{$totalPages})\n\n";

        if (!empty($category['description'])) {
            $message .= "{$category['description']}\n\n";
        }

        $message .= "Bu kategoriyadagi kinolar soni: {$totalMovies} ta";

        $movieButtons = [];
        foreach ($movies as $movie) {
            $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")];
        }

        $backButton = [Keyboard::getCallbackButton("🔙 Kategoriyalarga qaytish", "categories")];
        $movieButtons[] = $backButton;

        $keyboard = Keyboard::pagination("category_{$categoryId}", $page, $totalPages, $movieButtons);

        self::sendMenu($bot, $message, $keyboard);
    }

    public static function showRecommendationsMenu(Nutgram $bot, PDO $db, int $page = 1): void
    {
        $userId = $bot->userId();
        $perPage = Config::getItemsPerPage();
        $offset = ($page - 1) * $perPage;

        $recommendations = Movie::getRecommendations($db, $userId, $perPage, $offset);
        $totalRecommended = Movie::getRecommendationsCount($db, $userId);

        if (count($recommendations) <= 0) {
            $message = "💫 <b>Tavsiyalar</b>\n\n";
            $message .= "Hozircha sizga tavsiyalar yo'q. Ko'proq kinolarni ko'ring va yoqtiring.";

            self::sendMenu($bot, $message);
            return;
        }

        $totalPages = ceil($totalRecommended / $perPage);
        $message = "💫 <b>Tavsiyalar</b> (sahifa {$page}/{$totalPages})\n\n";
        $message .= "Qiziqishlaringiz asosida siz uchun maxsus tanlangan kinolar:";

        $movieButtons = [];
        foreach ($recommendations as $movie) {
            $score = isset($movie['recommendation_score']) ? " (" . number_format($movie['recommendation_score'], 1) . "✨)" : "";
            $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}{$score}", "movie_{$movie['id']}")];
        }

        $keyboard = Keyboard::pagination('recommendations', $page, $totalPages, $movieButtons);

        self::sendMenu($bot, $message, $keyboard);
    }

    public static function showMovieManageMenu(Nutgram $bot): void
    {
        State::clear($bot, ['state']);
        State::setScreen($bot, State::ADM_MOVIE);
        self::sendMenu($bot, Text::movieManage(), Keyboard::movieManageMenu());
    }

    public static function showCategoryManageMenu(Nutgram $bot, PDO $db): void
    {
        State::setScreen($bot, State::ADM_CATEGORY);
        self::sendMenu($bot, Text::categoryManage(), Keyboard::categoryManageMenu());
    }

    public static function showChannelManageMenu(Nutgram $bot, PDO $db): void
    {
        State::setScreen($bot, State::ADM_CHANNEL);
        ChannelService::showChannels($bot, $db);
    }

    public static function showStatisticManageMenu(Nutgram $bot, PDO $db): void
    {
        StatisticsService::showStats($bot, $db);
    }

    public static function showBroadcastMenu(Nutgram $bot): void
    {
        State::setState($bot, 'broadcast_message');

        $message = "📣 <b>Foydalanuvchilarga yubormoqchi bo'lgan xabarni kiriting:</b>";

        self::sendMenu($bot, $message, Keyboard::cancel());
    }

    public static function showAddMovieGuide(Nutgram $bot): void
    {
        State::setState($bot, 'add_movie_title');
        self::sendMenu($bot, Text::addMovie(), Keyboard::cancel());
    }

    public static function showDelMovieMenu(Nutgram $bot): void
    {
        State::set($bot, 'state', 'delete_movie_id');

        $message = "🗑 <b>O'chirish uchun kino ID raqamini kiriting:</b>";

        self::sendMenu($bot, $message, Keyboard::cancel());
    }

    public static function showEditMovieGuide(Nutgram $bot): void
    {
        State::setState($bot, 'edit_movie_id');
        self::sendMenu($bot, Text::editMovie(), Keyboard::cancel());
    }

    public static function showAddCategoryGuide(Nutgram $bot): void
    {
        self::sendMenu($bot, Text::addCategory(), Keyboard::cancel());
    }

    public static function showAddVideoGuide(Nutgram $bot, array $movie): void
    {
        self::sendMenu($bot, Text::addVideo($movie), Keyboard::cancel());
    }

    public static function showAddChannelGuide(Nutgram $bot): void
    {
        self::sendMenu($bot, Text::addChannel(), Keyboard::cancel());
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
