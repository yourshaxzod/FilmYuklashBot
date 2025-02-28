<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Models\{Movie, Video};
use App\Services\VideoService;
use App\Helpers\{State, Formatter, Keyboard, Text};
use PDO;

class MediaHandler
{
    /**
     * Barcha media handlerlari ro'yxatga olish.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     */
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onPhoto(function (Nutgram $bot) use ($db) {
            $state = State::getState($bot);

            if ($state === 'add_movie_photo') {
                self::handleMoviePosterUpload($bot, $db);
                return;
            }

            if (str_starts_with($state ?? '', 'edit_movie_photo_')) {
                $movieId = (int)substr($state, strlen('edit_movie_photo_'));
                self::handleMoviePosterEdit($bot, $db, $movieId);
                return;
            }
        });

        $bot->onVideo(function (Nutgram $bot) use ($db) {
            $state = State::getState($bot);
            $movieId = $bot->getUserData('movie_id');

            if ($state === 'add_video' && $movieId) {
                VideoService::processUploadedVideo($bot, $db);
                return;
            }

            if (str_starts_with($state ?? '', 'edit_video_') && $movieId) {
                $videoId = (int)substr($state, strlen('edit_video_'));
                self::handleVideoEdit($bot, $db, (int)$movieId, $videoId);
                return;
            }
        });
    }

    /**
     * Kino posteri yuklashni qayta ishlash.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     */
    private static function handleMoviePosterUpload(Nutgram $bot, PDO $db): void
    {
        $photo = $bot->message()->photo;
        $fileId = end($photo)->file_id;

        State::setState($bot, 'movie_photo', $fileId);
        State::setState($bot, 'state', 'add_movie_confirm');

        $title = $bot->getUserData('movie_title');
        $description = $bot->getUserData('movie_description');
        $year = $bot->getUserData('movie_year');

        $message = "🎬 <b>Yangi kino ma'lumotlari:</b>\n\n";
        $message .= "📋 <b>Nomi:</b> {$title}\n";
        $message .= "📅 <b>Yil:</b> {$year}\n";
        $message .= "📝 <b>Tavsif:</b> {$description}\n\n";
        $message .= "Kino qo'shishni tasdiqlaysizmi?";

        $bot->sendPhoto(
            photo: $fileId,
            caption: $message,
            parse_mode: 'HTML',
            reply_markup: Keyboard::Confirm()
        );
    }

    /**
     * Kino posterini tahrirlashni qayta ishlash.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param int $movieId Kino ID.
     */
    private static function handleMoviePosterEdit(Nutgram $bot, PDO $db, int $movieId): void
    {
        try {
            $movie = Movie::findByID($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::MovieNotFound());
                return;
            }

            $photo = $bot->message()->photo;
            $fileId = end($photo)->file_id;

            Movie::update($db, $movieId, ['file_id' => $fileId]);

            $bot->sendPhoto(
                photo: $fileId,
                caption: "✅ Kino posteri muvaffaqiyatli yangilandi!",
                reply_markup: Keyboard::MainMenu($bot)
            );

            State::clearState($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Kino posterini yangilashda xatolik: ", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
            State::clearState($bot);
        }
    }

    /**
     * Video tahrirlashni qayta ishlash.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param int $movieId Kino ID.
     * @param int $videoId Video ID.
     */
    private static function handleVideoEdit(Nutgram $bot, PDO $db, int $movieId, int $videoId): void
    {
        try {
            $video = Video::findByID($db, $videoId);
            if (!$video || $video['movie_id'] != $movieId) {
                $bot->sendMessage(text: Text::VideoNotFound());
                return;
            }

            $uploadedVideo = $bot->message()->video;

            Video::update($db, $videoId, [
                'file_id' => $uploadedVideo->file_id
            ]);

            $formattedSize = '';
            $formattedDuration = '';

            if (isset($uploadedVideo->file_size)) {
                $formattedSize = "\n📦 <b>Hajmi:</b> " . Formatter::formatFileSize($uploadedVideo->file_size);
            }

            if (isset($uploadedVideo->duration)) {
                $formattedDuration = "\n⏱ <b>Davomiyligi:</b> " . Formatter::formatDuration($uploadedVideo->duration);
            }

            $message = "✅ Video muvaffaqiyatli yangilandi!\n\n" .
                "🎬 <b>Kino:</b> {$video['movie_title']}\n" .
                "📹 <b>Video:</b> {$video['title']}\n" .
                "🔢 <b>Qism:</b> {$video['id']}" .
                $formattedDuration .
                $formattedSize;

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::MainMenu($bot)
            );

            State::clearState($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Videoni yangilashda xatolik", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
            State::clearState($bot);
        }
    }
}
