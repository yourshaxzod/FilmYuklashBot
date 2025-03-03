<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video, Category};
use App\Helpers\{Formatter, Keyboard, Validator, Menu, State, Text, Config};
use PDO;

class MovieService
{
    public static function search(Nutgram $bot, PDO $db, string $query, int $page = 1): void
    {
        try {
            $query = trim($query);

            State::set($bot, 'last_search_query', $query);

            if (Formatter::isNumericString($query)) {
                $movie = Movie::findById($db, (int)$query, $bot->userId());
                if ($movie) {
                    self::showMovie($bot, $db, $movie);
                    return;
                }
            }

            $perPage = Config::getItemsPerPage();
            $offset = ($page - 1) * $perPage;

            $movies = Movie::searchByText($db, $query, $bot->userId(), $perPage, $offset);
            $totalFound = Movie::searchCount($db, $query);

            if (empty($movies)) {
                $bot->sendMessage(
                    text: "😞 <b>Hech narsa topilmadi.</b>",
                    parse_mode: ParseMode::HTML,
                );
                return;
            }

            if (count($movies) === 1 && $totalFound === 1) {
                self::showMovie($bot, $db, $movies[0]);
                return;
            }

            $totalPages = ceil($totalFound / $perPage);

            $message = "🔍 <b>Qidiruv natijalari:</b> \"{$query}\" (sahifa {$page}/{$totalPages})\n\n";
            $message .= "✅ <b>Topildi:</b> {$totalFound} ta kino.\n";
            $message .= "Quyidagilardan birini tanlang:";

            $movieButtons = [];
            foreach ($movies as $movie) {
                $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")];
            }

            $keyboard = Keyboard::pagination('search', $page, $totalPages, $movieButtons);

            if ($page === 1) {
                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            } else {
                $bot->editMessageText(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Qidirishda xatolik yuz berdi: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    public static function showMovie(Nutgram $bot, PDO $db, $movie): void
    {
        try {
            if (is_int($movie)) {
                $movie = Movie::findById($db, $movie, $bot->userId());
                if (!$movie) {
                    throw new \Exception("Kino topilmadi.");
                }
            }

            Movie::addView($db, $bot->userId(), $movie['id']);

            TavsiyaService::updateUserInterests($db, $bot->userId(), $movie['id'], 'view');

            $videos = Video::getAllByMovieId($db, $movie['id']);
            $videoCount = count($videos);
            $categories = Category::getByMovieId($db, $movie['id']);

            $text = Text::movieInfo($movie, $videoCount, $categories, Validator::isAdmin($bot));

            $extras = [
                'video_count' => $videoCount,
                'is_liked' => $movie['is_liked'] ?? false,
                'categories' => $categories
            ];

            if (!empty($movie['file_id'])) {
                $bot->sendPhoto(
                    photo: $movie['file_id'],
                    caption: $text,
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::movieActions($movie, $extras, Validator::isAdmin($bot))
                );
            } else {
                $bot->sendMessage(
                    text: $text,
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::movieActions($movie, $extras, Validator::isAdmin($bot))
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Show movies list for admin
     * 
     * @param Nutgram $bot Bot instance
     * @param PDO $db Database connection
     * @param int $page Page number
     * @return void
     */
    public static function showMoviesList(Nutgram $bot, PDO $db, int $page = 1): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(
                    text: Text::noPermission(),
                    reply_markup: Keyboard::mainMenu($bot)
                );
                return;
            }

            // Get pagination parameters
            $perPage = Config::getItemsPerPage();
            $offset = ($page - 1) * $perPage;

            // Get movies
            $movies = Movie::getAll($db, $perPage, $offset);
            $totalMovies = Movie::getCountMovies($db);

            // If no movies found
            if (empty($movies)) {
                $bot->sendMessage(
                    text: "😞 Bazada hech qanday kino yo'q.",
                    reply_markup: Keyboard::movieManageMenu()
                );
                return;
            }

            // Calculate total pages
            $totalPages = ceil($totalMovies / $perPage);

            // Show movies list with pagination
            $message = "📋 <b>Kinolar ro'yxati</b> (sahifa {$page}/{$totalPages})\n\n";
            $message .= "Jami: {$totalMovies} ta kino\n\n";

            // Add movie info to message
            foreach ($movies as $index => $movie) {
                $message .= ($index + 1) . ". 🆔<b>{$movie['id']}</b> - {$movie['title']} ({$movie['year']})\n";
                $message .= "   👁 {$movie['views']} | ❤️ {$movie['likes']} | 📹 {$movie['video_count']}\n";
            }

            // Create navigation buttons
            $buttons = [];

            // Add admin action buttons
            $buttons[] = [
                Keyboard::getCallbackButton("➕ Qo'shish", "add_movie"),
                Keyboard::getCallbackButton("🔄 Yangilash", "refresh_movies_list")
            ];

            // Add back button
            $buttons[] = [
                Keyboard::getCallbackButton("🔙 Orqaga", "back_to_admin")
            ];

            // Add pagination buttons
            $keyboard = Keyboard::pagination('movies_list', $page, $totalPages, $buttons);

            // Send message
            if ($page === 1) {
                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            } else {
                $bot->editMessageText(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Kinolar ro'yxatini olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::movieManageMenu()
            );
        }
    }

    /**
     * Confirm movie deletion
     * 
     * @param Nutgram $bot Bot instance
     * @param PDO $db Database connection
     * @param int $movieId Movie ID
     * @return void
     */
    public static function confirmDeleteMovie(Nutgram $bot, PDO $db, int $movieId): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

            // Get movie details
            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            // Get additional data
            $videoCount = Video::getCountByMovieId($db, $movieId);
            $categories = Category::getByMovieId($db, $movieId);

            // Create confirmation message
            $message = "🗑 <b>Kinoni o'chirish</b>\n\n";
            $message .= Text::movieInfo($movie, $videoCount, $categories);
            $message .= "\n\nKinoni o'chirishni tasdiqlaysizmi? Bu amal qaytarib bo'lmaydi va barcha video qismlar ham o'chiriladi!";

            // Update message with confirm buttons
            $bot->editMessageText(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::confirmDelete('movie', $movieId)
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Get trending movies for cache
     * 
     * @param PDO $db Database connection
     * @param int $limit Limit
     * @return array Movies data
     */
    public static function getTrendingForCache(PDO $db, int $limit = 10): array
    {
        try {
            $movies = Movie::getTrendingMovies($db, $limit);

            // Add categories to movies
            foreach ($movies as &$movie) {
                $movie['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (\Exception $e) {
            error_log("Trend kinolarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recommended movies by user interests
     * 
     * @param Nutgram $bot Bot instance 
     * @param PDO $db Database connection
     * @param int $limit Limit
     * @return array Recommended movies
     */
    public static function getRecommendedMovies(Nutgram $bot, PDO $db, int $limit = 10): array
    {
        try {
            $userId = $bot->userId();

            // Get user interests
            $userInterests = TavsiyaService::analyzeUserInterests($db, $userId);

            // If user has no interests yet, return trending movies
            if (empty($userInterests['top_categories'])) {
                return self::getTrendingForCache($db, $limit);
            }

            // Get recommendations
            $recommendations = Movie::getRecommendations($db, $userId, $limit);

            // If no recommendations, return trending movies
            if (empty($recommendations)) {
                return self::getTrendingForCache($db, $limit);
            }

            return $recommendations;
        } catch (\Exception $e) {
            error_log("Tavsiyalarni olishda xatolik: " . $e->getMessage());
            return self::getTrendingForCache($db, $limit);
        }
    }
}
