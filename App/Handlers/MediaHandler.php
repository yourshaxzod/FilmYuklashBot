<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Models\{Movie, Video};
use App\Helpers\{Menu, State, Validator, Formatter, Keyboard};
use PDO;

class MediaHandler
{
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

            $bot->sendMessage("Rasm qabul qilindi, lekin hozir bu kerak emas.");
        });

        $bot->onVideo(function (Nutgram $bot) use ($db) {
            $state = State::getState($bot);
            $movieId = $bot->getUserData('movie_id');

            if ($state === 'add_video' && $movieId) {
                self::handleVideoUpload($bot, $db, (int)$movieId);
                return;
            }

            if (str_starts_with($state ?? '', 'edit_video_') && $movieId) {
                $videoId = (int)substr($state, strlen('edit_video_'));
                self::handleVideoEdit($bot, $db, (int)$movieId, $videoId);
                return;
            }

            $bot->sendMessage("Video qabul qilindi, lekin hozir bu kerak emas.");
        });
    }

    private static function handleMoviePosterUpload(Nutgram $bot, PDO $db): void
    {
        $photo = $bot->message()->photo;
        $fileId = end($photo)->file_id;

        State::setState($bot, 'movie_photo', $fileId);
        State::setState($bot, 'state', 'add_movie_confirm');

        $title = $bot->getUserData('movie_title');
        $code = $bot->getUserData('movie_code');
        $description = $bot->getUserData('movie_description');
        $year = $bot->getUserData('movie_year', date('Y'));

        $message = "🎬 <b>Yangi kino ma'lumotlari:</b>\n\n";
        $message .= "📋 <b>Nomi:</b> {$title}\n";
        $message .= "🔢 <b>Kod:</b> {$code}\n";
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

    private static function handleMoviePosterEdit(Nutgram $bot, PDO $db, int $movieId): void
    {
        $movie = Movie::find($db, $movieId);
        if (!$movie) {
            $bot->sendMessage("Kino topilmadi!");
            return;
        }

        $photo = $bot->message()->photo;
        $fileId = end($photo)->file_id;

        try {
            Movie::update($db, $movieId, ['photo_file_id' => $fileId]);

            $bot->sendPhoto(
                photo: $fileId,
                caption: "✅ Kino posteri muvaffaqiyatli yangilandi!",
                reply_markup: Keyboard::MainMenu($bot)
            );

            State::clearState($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Xatolik yuz berdi: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
            State::clearState($bot);
        }
    }

    private static function handleVideoUpload(Nutgram $bot, PDO $db, int $movieId): void
    {
        $movie = Movie::find($db, $movieId);
        if (!$movie) {
            $bot->sendMessage("Kino topilmadi!");
            return;
        }

        $video = $bot->message()->video;

        if ($video->file_size > 50 * 1024 * 1024) {
            $bot->sendMessage(
                text: "⚠️ Video hajmi 50MB dan oshmasligi kerak!",
                reply_markup: Keyboard::Cancel()
            );
            return;
        }

        $title = $bot->getUserData('video_title');
        $partNumber = (int)$bot->getUserData('video_part', Video::getNextPartNumber($db, $movieId));

        if (empty($title)) {
            State::setState($bot, 'state', 'add_video_title');
            State::setState($bot, 'video_file_id', $video->file_id);
            State::setState($bot, 'video_duration', $video->duration);
            State::setState($bot, 'video_file_size', $video->file_size);

            $bot->sendMessage(
                text: "🎬 Video qabul qilindi!\n\nEndi, iltimos, video sarlavhasini kiriting:",
                reply_markup: Keyboard::Cancel()
            );
            return;
        }

        try {
            $videoData = [
                'movie_id' => $movieId,
                'title' => $title,
                'part_number' => $partNumber,
                'video_file_id' => $video->file_id,
                'duration' => $video->duration,
                'file_size' => $video->file_size
            ];

            $videoId = Video::create($db, $videoData);

            $formattedSize = Formatter::formatFileSize($video->file_size);
            $formattedDuration = Formatter::formatDuration($video->duration);

            $bot->sendMessage(
                text: "✅ Video muvaffaqiyatli qo'shildi!\n\n" .
                    "🎬 <b>Kino:</b> {$movie['title']}\n" .
                    "📹 <b>Video:</b> {$title}\n" .
                    "🔢 <b>Qism:</b> {$partNumber}\n" .
                    "⏱ <b>Davomiyligi:</b> {$formattedDuration}\n" .
                    "📦 <b>Hajmi:</b> {$formattedSize}",
                parse_mode: 'HTML',
                reply_markup: Keyboard::MainMenu($bot)
            );

            State::clearState($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Xatolik yuz berdi: " . $e->getMessage(),
                reply_markup: Keyboard::Cancel()
            );
        }
    }

    private static function handleVideoEdit(Nutgram $bot, PDO $db, int $movieId, int $videoId): void
    {
        $video = Video::find($db, $videoId);
        if (!$video || $video['movie_id'] != $movieId) {
            $bot->sendMessage("Video topilmadi!");
            return;
        }

        $uploadedVideo = $bot->message()->video;

        try {
            Video::update($db, $videoId, [
                'video_file_id' => $uploadedVideo->file_id,
                'duration' => $uploadedVideo->duration,
                'file_size' => $uploadedVideo->file_size
            ]);

            $formattedSize = Formatter::formatFileSize($uploadedVideo->file_size);
            $formattedDuration = Formatter::formatDuration($uploadedVideo->duration);

            $bot->sendMessage(
                text: "✅ Video muvaffaqiyatli yangilandi!\n\n" .
                    "🎬 <b>Kino:</b> {$video['movie_title']}\n" .
                    "📹 <b>Video:</b> {$video['title']}\n" .
                    "🔢 <b>Qism:</b> {$video['part_number']}\n" .
                    "⏱ <b>Davomiyligi:</b> {$formattedDuration}\n" .
                    "📦 <b>Hajmi:</b> {$formattedSize}",
                parse_mode: 'HTML',
                reply_markup: Keyboard::MainMenu($bot)
            );

            State::clearState($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Xatolik yuz berdi: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
            State::clearState($bot);
        }
    }
}
