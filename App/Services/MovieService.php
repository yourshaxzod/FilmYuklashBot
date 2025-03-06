<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video, Category};
use App\Helpers\{Formatter, Keyboard, Validator, State, Text, Config};
use PDO;
use SergiX44\Nutgram\Conversations\InlineMenu;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class MovieService
{
    public static function search(Nutgram $bot, PDO $db, string $query)
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

            $movies = Movie::searchByText($db, $query, $bot->userId(), $perPage, 0);
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

            $message = "🔍 <b>Qidiruv natijalari:</b> {$query}\n\n";
            $message .= "✅ <b>Topildi:</b> {$totalFound} ta kino.\n";
            $message .= "Quyidagilardan birini tanlang:";

            $keyboard = InlineKeyboardMarkup::make();

            foreach ($movies as $movie) {
                $keyboard->addRow(InlineKeyboardButton::make("🎬 {$movie['title']}", "movie_{$movie['id']}"));
            }

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

    public static function showMovie(Nutgram $bot, PDO $db, $movie)
    {
        try {
            if (is_int($movie)) {
                $movie = Movie::findById($db, $movie, $bot->userId());
                if (!$movie) {
                    throw new \Exception("Kino topilmadi.");
                }
            }

            Movie::addView($db, $bot->userId(), $movie['id']);

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

    public static function showMoviesList(Nutgram $bot, PDO $db, int $page = 1)
    {
        try {
            $perPage = Config::getItemsPerPage();
            $offset = ($page - 1) * $perPage;

            $movies = Movie::getAll($db, $perPage, $offset);
            $totalMovies = Movie::getCountMovies($db);

            if (empty($movies)) {
                $bot->sendMessage(
                    text: "😞 Bazada hech qanday kino yo'q.",
                    reply_markup: Keyboard::movieManageMenu()
                );
                return;
            }

            $totalPages = ceil($totalMovies / $perPage);

            $message = "📋 <b>Kinolar ro'yxati</b> (sahifa {$page}/{$totalPages})\n\n";
            $message .= "📊 <b>Jami:</b> {$totalMovies} ta.\n\n";

            foreach ($movies as $index => $movie) {
                $message .= ($index + 1) . ". 🆔<b>{$movie['id']}</b> - {$movie['title']} ({$movie['year']})\n";
            }

            $keyboard = Keyboard::pagination('movies_list', $page, $totalPages);

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

    public static function confirmDeleteMovie(Nutgram $bot, PDO $db, int $movieId)
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

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

    public static function getTrendingForCache(PDO $db, int $limit = 10)
    {
        try {
            $movies = Movie::getTrendingMovies($db, $limit);

            foreach ($movies as &$movie) {
                $movie['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (\Exception $e) {
            error_log("Trend kinolarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }

    public static function getRecommendedMovies(Nutgram $bot, PDO $db, int $limit = 10)
    {
        try {
            $userId = $bot->userId();

            $userInterests = RecommendationService::analyzeUserInterests($db, $userId);

            if (empty($userInterests['top_categories'])) {
                return self::getTrendingForCache($db, $limit);
            }

            $recommendations = Movie::getRecommendations($db, $userId, $limit);

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
