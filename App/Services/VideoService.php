<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video};
use App\Helpers\{Button, Formatter, Keyboard, Validator, State, Text};
use PDO;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class VideoService
{
    /**
     * Show videos for a movie
     */
    public static function showVideos(Nutgram $bot, PDO $db, int $movieId, bool $isAdmin = false): void
    {
        try {
            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(
                    text: Text::movieNotFound(),
                    reply_markup: Keyboard::mainMenu($bot)
                );
                return;
            }

            $videos = Video::getAllByMovieId($db, $movieId);
            
            if (empty($videos)) {
                $message = "📹 <b>{$movie['title']}</b> videolari\n\n";
                $message .= "Bu kino uchun hozircha videolar yo'q.";
                
                $keyboard = InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make(
                        text: "🔙 Kinoga qaytish",
                        callback_data: "movie_{$movieId}"
                    )
                );
                
                if ($isAdmin) {
                    $keyboard->addRow(
                        InlineKeyboardButton::make(
                            text: "➕ Video qo'shish",
                            callback_data: "add_video_{$movieId}"
                        )
                    );
                }
                
                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
                return;
            }

            $message = "📹 <b>{$movie['title']}</b> videolari\n\n";
            $message .= "Jami: " . count($videos) . " ta video\n";
            $message .= "Kerakli qismni tanlang:";

            $keyboard = Keyboard::videoList($videos, $movieId, $isAdmin);

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::error("Videolarni ko'rsatishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Start adding a video
     */
    public static function startAddVideo(Nutgram $bot, PDO $db, int $movieId): void
    {
        if (!Validator::isAdmin($bot)) {
            $bot->sendMessage(text: Text::noPermission());
            return;
        }

        try {
            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            State::set($bot, 'state', 'add_video');
            State::set($bot, 'movie_id', $movieId);

            $nextPart = Video::getNextPartNumber($db, $movieId);

            $message = "📹 <b>Video qo'shish</b>\n\n" .
                "🎬 <b>Kino:</b> {$movie['title']}\n" .
                "🔢 <b>Keyingi qism raqami:</b> {$nextPart}\n\n" .
                "Video sarlavhasini kiriting:";

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::cancel()
            );

            State::set($bot, 'state', 'add_video_title');
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::error("Video qo'shishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Play a video
     */
    public static function playVideo(Nutgram $bot, PDO $db, int $videoId): void
    {
        try {
            $video = Video::findById($db, $videoId);
            if (!$video) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Video topilmadi",
                    show_alert: true
                );
                return;
            }

            // Update view count
            Movie::addView($db, $bot->userId(), $video['movie_id']);

            // Send the video
            $bot->sendVideo(
                video: $video['file_id'],
                caption: "🎬 <b>{$video['movie_title']}</b>\n📹 <b>{$video['title']}</b>",
                parse_mode: ParseMode::HTML
            );

            $bot->answerCallbackQuery();
        } catch (\Exception $e) {
            $bot->answerCallbackQuery(
                text: "⚠️ Videoni yuklashda xatolik: " . $e->getMessage(),
                show_alert: true
            );
        }
    }

    /**
     * Delete a video
     */
    public static function deleteVideo(Nutgram $bot, PDO $db, int $videoId): void
    {
        if (!Validator::isAdmin($bot)) {
            $bot->sendMessage(text: Text::noPermission());
            return;
        }

        try {
            $video = Video::findById($db, $videoId);
            if (!$video) {
                $bot->sendMessage(text: Text::videoNotFound());
                return;
            }

            $movieId = $video['movie_id'];

            Video::delete($db, $videoId);

            $bot->sendMessage(
                text: "✅ Video muvaffaqiyatli o'chirildi!"
            );

            self::showVideos($bot, $db, $movieId, true);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::error("Videoni o'chirishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }
}