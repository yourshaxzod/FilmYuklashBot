<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video, Category};
use App\Helpers\{Formatter, Keyboard, Validator, State, Text, Config};
use PDO;

class MovieService
{
    /**
     * Search for movies by text
     */
    public static function search(Nutgram $bot, PDO $db, string $query): void
    {
        try {
            $query = trim($query);
            
            // Save the last search query for potential re-use
            State::set($bot, 'last_search_query', $query);

            // If the query is a number, try to find the movie by ID
            if (Validator::validateNumericInput($query)) {
                $movie = Movie::findById($db, (int)$query, $bot->userId());
                if ($movie) {
                    self::showMovie($bot, $db, $movie);
                    return;
                }
            }

            // Otherwise, search by title
            $movies = Movie::searchByText($db, $query, $bot->userId());

            if (empty($movies)) {
                $bot->sendMessage(
                    text: "😔 <b>Hech narsa topilmadi.</b>",
                    parse_mode: ParseMode::HTML
                );
                return;
            }

            // If only one movie found, show it directly
            if (count($movies) === 1) {
                self::showMovie($bot, $db, $movies[0]);
                return;
            }

            // Otherwise, show list of found movies
            $message = "🔍 <b>Qidiruv natijalari:</b> {$query}\n\n";
            $message .= "Topildi: " . count($movies) . " ta kino\n";
            $message .= "Kerakli kinoni tanlang:";

            $buttons = [];
            foreach ($movies as $movie) {
                $buttons[] = [
                    Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")
                ];
            }

            $keyboard = Keyboard::getInlineKeyboard($buttons);

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Qidirishda xatolik yuz berdi: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Show a movie
     */
    public static function showMovie(Nutgram $bot, PDO $db, $movie): void
    {
        try {
            if (is_int($movie)) {
                $movie = Movie::findById($db, $movie, $bot->userId());
                if (!$movie) {
                    throw new \Exception("Kino topilmadi.");
                }
            }

            // Record the view
            Movie::addView($db, $bot->userId(), $movie['id']);

            // Get related data
            $videos = Video::getAllByMovieId($db, $movie['id']);
            $videoCount = count($videos);
            $categories = Category::getByMovieId($db, $movie['id']);

            // Prepare the message text
            $text = Text::movieInfo($movie, $videoCount, $categories, Validator::isAdmin($bot));

            // Extras for keyboard
            $extras = [
                'video_count' => $videoCount,
                'is_liked' => $movie['is_liked'] ?? false,
                'categories' => $categories
            ];

            // Send as photo if file_id exists, otherwise as text
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
     * Show list of movies
     */
    public static function showMoviesList(Nutgram $bot, PDO $db): void
    {
        try {
            $movies = Movie::getAll($db);
            $totalMovies = Movie::getCountMovies($db);

            if (empty($movies)) {
                $bot->sendMessage(
                    text: "😞 Bazada hech qanday kino yo'q.",
                    reply_markup: Keyboard::movieManageMenu()
                );
                return;
            }

            $message = "📋 <b>Kinolar ro'yxati</b>\n\n";
            $message .= "📊 <b>Jami:</b> {$totalMovies} ta kino\n\n";

            foreach ($movies as $index => $movie) {
                $message .= ($index + 1) . ". 🆔<b>{$movie['id']}</b> - {$movie['title']} ({$movie['year']})\n";
            }

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::movieManageMenu()
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Kinolar ro'yxatini olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::movieManageMenu()
            );
        }
    }

    /**
     * Confirm delete movie
     */
    public static function confirmDeleteMovie(Nutgram $bot, PDO $db, int $movieId): void
    {
        try {
            // Check for admin permission
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

            // Find the movie
            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            $videoCount = Video::getCountByMovieId($db, $movieId);
            $categories = Category::getByMovieId($db, $movieId);

            $message = "🗑 <b>Kinoni o'chirish</b>\n\n";
            $message .= Text::movieInfo($movie, $videoCount, $categories);
            $message .= "\n\nKinoni o'chirishni tasdiqlaysizmi? Bu amal qaytarib bo'lmaydi va barcha video qismlar ham o'chiriladi!";

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
     */
    public static function getTrendingForCache(PDO $db, int $limit = 10): array
    {
        try {
            $movies = Movie::getTrendingMovies($db, $limit);

            foreach ($movies as &$movie) {
                $movie['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (\Exception $e) {
            error_log("Error getting trending movies: " . $e->getMessage());
            return [];
        }
    }
}