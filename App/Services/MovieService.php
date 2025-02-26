<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video};
use App\Helpers\{Formatter, Keyboard, Validator, Menu, State, Text};
use PDO;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class MovieService
{
    public static function search(Nutgram $bot, PDO $db, string $query): void
    {
        try {
            $query = trim($query);

            // Save last search query for pagination
            State::setState($bot, 'last_search_query', $query);

            // If query is numeric, try to find movie by code
            if (Formatter::isNumericString($query)) {
                $movie = Movie::findByCode($db, $query, $bot->userId());
                if ($movie) {
                    self::show($bot, $db, $movie);
                    return;
                }
            }

            $movies = Movie::search($db, $query, $bot->userId());
            if (empty($movies)) {
                $bot->sendMessage(
                    text: "🔍 <b>Qidiruv natijalari:</b>\n\n" .
                        "❌ <b>\"{$query}\"</b> bo'yicha hech narsa topilmadi.",
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::MainMenu($bot)
                );
                State::clearState($bot);
                return;
            }

            if (count($movies) === 1) {
                self::show($bot, $db, $movies[0]);
                return;
            }

            // Multiple results
            $message = "🔍 <b>Qidiruv natijalari:</b> \"{$query}\"\n\n";
            $message .= "Topildi: " . count($movies) . " ta kino\n";
            $message .= "Kerakli kinoni tanlang:";

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::SearchResults($movies)
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Qidirishda xatolik yuz berdi: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    public static function show(Nutgram $bot, PDO $db, array|int $movie): void
    {
        try {
            if (is_int($movie)) {
                $movie = Movie::find($db, $movie, $bot->userId());
                if (!$movie) {
                    throw new \Exception("Kino topilmadi.");
                }
            }

            // Increment view count
            Movie::addView($db, $bot->userId(), $movie['id']);

            // Get videos for this movie
            $videos = Video::getAllByMovie($db, $movie['id']);

            // Create movie info text
            $text = Text::MovieInfo($movie, Validator::isAdmin($bot));

            // Add video count info
            $videoCount = count($videos);
            $text .= "\n📹 <b>Video qismlar:</b> " . $videoCount . " ta\n";

            // Add views and likes info
            $text .= "👁 <b>Ko'rishlar:</b> " . Formatter::formatNumber($movie['views']) . "\n";
            $text .= "❤️ <b>Yoqtirishlar:</b> " . Formatter::formatNumber($movie['likes']) . "\n";

            if ($movie['photo_file_id']) {
                $bot->sendPhoto(
                    photo: $movie['photo_file_id'],
                    caption: $text,
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::MovieActions($movie, ['video_count' => $videoCount, 'is_liked' => $movie['is_liked'] ?? false], Validator::isAdmin($bot))
                );
            } else {
                $bot->sendMessage(
                    text: $text,
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::MovieActions($movie, ['video_count' => $videoCount, 'is_liked' => $movie['is_liked'] ?? false], Validator::isAdmin($bot))
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    public static function showFavorites(Nutgram $bot, PDO $db): void
    {
        try {
            $userId = $bot->userId();

            // Get user's liked movies
            $stmt = $db->prepare("
                SELECT m.* 
                FROM movies m
                JOIN user_likes ul ON m.id = ul.movie_id
                WHERE ul.user_id = ?
                ORDER BY ul.liked_at DESC
            ");
            $stmt->execute([$userId]);
            $likedMovies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($likedMovies)) {
                $bot->sendMessage(
                    text: "❤️ <b>Sevimli kinolar</b>\n\n" .
                        "Sizda hali sevimli kinolar yo'q.",
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::MainMenu($bot)
                );
                return;
            }

            $message = "❤️ <b>Sevimli kinolar</b>\n\n";
            $message .= "Sevimli kinolaringiz soni: " . count($likedMovies) . " ta\n";
            $message .= "Kerakli kinoni tanlang:";

            // Create keyboard with movie list
            $keyboard = Keyboard::SearchResults($likedMovies);

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Sevimli kinolarni olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    public static function showTrending(Nutgram $bot, PDO $db): void
    {
        try {
            $userId = $bot->userId();
            $perPage = 10;
            $trending = Movie::getTrending($db, $perPage, $userId);

            if (empty($trending)) {
                $bot->sendMessage(
                    text: "🔥 <b>Trend kinolar</b>\n\n" .
                        "Hozircha trend kinolar yo'q.",
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::MainMenu($bot)
                );
                return;
            }

            $totalMovies = Movie::getCount($db);
            $totalPages = ceil($totalMovies / $perPage);

            $message = "🔥 <b>Trend kinolar</b> (sahifa 1/{$totalPages})\n\n";
            $message .= "Eng ko'p ko'rilgan va yoqtirilgan kinolar:";

            // Create keyboard with trending movies and pagination
            $keyboard = Keyboard::Pagination('trending', 1, $totalPages, [
                array_map(function ($movie) {
                    return Keyboard::getCallbackButton($movie['title'], "movie_{$movie['id']}");
                }, $trending)
            ]);

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Trend kinolarni olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    public static function showMovie(Nutgram $bot, PDO $db, int $movieId): void
    {
        $movie = Movie::find($db, $movieId, $bot->userId());
        if (!$movie) {
            $bot->sendMessage(
                text: "⚠️ Kino topilmadi!",
                reply_markup: Keyboard::MainMenu($bot)
            );
            return;
        }

        self::show($bot, $db, $movie);
    }

    public static function showMovieVideos(Nutgram $bot, PDO $db, int $movieId): void
    {
        try {
            $movie = Movie::find($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(
                    text: "⚠️ Kino topilmadi!",
                    reply_markup: Keyboard::MainMenu($bot)
                );
                return;
            }

            $videos = Video::getAllByMovie($db, $movieId);

            if (empty($videos)) {
                $bot->sendMessage(
                    text: "📹 <b>{$movie['title']}</b> videolari\n\n" .
                        "Bu kino uchun videolar mavjud emas.",
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::MainMenu($bot)
                );
                return;
            }

            $message = "📹 <b>{$movie['title']}</b> videolari\n\n";
            $message .= "Jami: " . count($videos) . " ta video\n";
            $message .= "Kerakli qismni tanlang:";

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::VideoList($videos, $movieId)
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Videolarni olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    public static function playVideo(Nutgram $bot, PDO $db, int $videoId): void
    {
        try {
            $video = Video::find($db, $videoId);
            if (!$video) {
                $bot->sendMessage(
                    text: "⚠️ Video topilmadi!",
                    reply_markup: Keyboard::MainMenu($bot)
                );
                return;
            }

            // Send video
            $bot->sendVideo(
                video: $video['video_file_id'],
                caption: "{$video['movie_title']} - {$video['title']} ({$video['part_number']}-qism)",
                parse_mode: ParseMode::HTML,
                supports_streaming: true
            );

            // Update view count for the movie
            Movie::addView($db, $bot->userId(), $video['movie_id']);

            // Get next video if exists
            $nextVideo = Video::findByPart($db, $video['movie_id'], $video['part_number'] + 1);

            if ($nextVideo) {
                $bot->sendMessage(
                    text: "⏭ <b>Keyingi qism:</b> {$nextVideo['title']} ({$nextVideo['part_number']}-qism)",
                    parse_mode: ParseMode::HTML,
                    reply_markup: InlineKeyboardMarkup::make()
                        ->addRow(
                            InlineKeyboardButton::make("▶️ Keyingi qismni ko'rish", callback_data: "play_video_{$nextVideo['id']}")
                        )
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Videoni yuborishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }
}
