<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Models\{Category, Movie, Video};
use App\Services\VideoService;
use App\Helpers\{State, Formatter, Keyboard, Text, Menu};
use App\Services\MovieService;
use PDO;
class MediaHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onPhoto(function (Nutgram $bot) use ($db) {
            $state = State::get($bot);

            if (!$state) {
                return;
            }

            if ($state === 'add_movie_photo') {
                self::handleMoviePosterUpload($bot, $db);
                return;
            }

            if (preg_match('/^edit_movie_photo_(\d+)$/', $state, $matches)) {
                $movieId = (int)$matches[1];
                self::handleMoviePosterEdit($bot, $db, $movieId);
                return;
            }

            if ($state === 'add_category_image') {
                self::handleCategoryImageUpload($bot, $db);
                return;
            }

            if (preg_match('/^edit_category_image_(\d+)$/', $state, $matches)) {
                $categoryId = (int)$matches[1];
                self::handleCategoryImageEdit($bot, $db, $categoryId);
                return;
            }
        });

        $bot->onVideo(function (Nutgram $bot) use ($db) {
            $state = State::get($bot);
            $movieId = State::get($bot, 'movie_id');

            if (!$state) {
                return;
            }

            if ($state === 'add_video' && $movieId) {
                self::handleVideoUpload($bot, $db);
                return;
            }

            if (preg_match('/^edit_video_(\d+)$/', $state, $matches)) {
                $videoId = (int)$matches[1];
                self::handleVideoEdit($bot, $db, $videoId);
                return;
            }
        });

        $bot->onDocument(function (Nutgram $bot) use ($db) {
            $state = State::get($bot);
            $movieId = State::get($bot, 'movie_id');

            if (!$state) {
                return;
            }

            $document = $bot->message()->document;
            if (!isset($document->mime_type) || !str_starts_with($document->mime_type, 'video/')) {
                $bot->sendMessage("⚠️ Iltimos, video fayl yuboring.");
                return;
            }

            if ($state === 'add_video' && $movieId) {
                self::handleDocumentVideoUpload($bot, $db);
                return;
            }

            if (preg_match('/^edit_video_(\d+)$/', $state, $matches)) {
                $videoId = (int)$matches[1];
                self::handleDocumentVideoEdit($bot, $db, $videoId);
                return;
            }
        });
    }

    private static function handleMoviePosterUpload(Nutgram $bot, PDO $db): void
    {
        $photos = $bot->message()->photo;
        $fileId = end($photos)->file_id;

        State::set($bot, 'movie_photo', $fileId);

        $title = State::get($bot, 'movie_title');
        $description = State::get($bot, 'movie_description');
        $year = State::get($bot, 'movie_year');

        $message = "🎬 <b>Yangi kino ma'lumotlari:</b>\n\n";
        $message .= "📋 <b>Nomi:</b> {$title}\n";
        $message .= "📅 <b>Yil:</b> {$year}\n";
        $message .= "📝 <b>Tavsif:</b> {$description}\n\n";

        $message .= "Kino ma'lumotlari to'g'rimi? Tasdiqlashdan oldin kategoriyalarni tanlashingiz mumkin.";

        $bot->sendPhoto(
            photo: $fileId,
            caption: $message,
            parse_mode: 'HTML'
        );

        self::offerCategorySelection($bot, $db);

        State::set($bot, 'state', 'add_movie_confirm');
    }

    private static function offerCategorySelection(Nutgram $bot, PDO $db): void
    {
        try {
            $categories = Category::getAll($db);

            if (empty($categories)) {
                $bot->sendMessage(
                    text: "⚠️ Hech qanday kategoriya topilmadi. Davom etish uchun quyidagi tugmalardan foydalaning:",
                    reply_markup: Keyboard::confirm()
                );
                return;
            }

            $selectedIds = State::get($bot, 'selected_categories') ?? [];

            $message = "🏷 <b>Kategoriyalar</b>\n\nKino uchun bir yoki bir nechta kategoriyalarni tanlang:";
            $keyboard = Keyboard::categorySelector($categories, $selectedIds);

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Kategoriyalarni yuklashda xatolik. Davom etish uchun quyidagi tugmalardan foydalaning:",
                reply_markup: Keyboard::confirm()
            );
        }
    }

    private static function handleMoviePosterEdit(Nutgram $bot, PDO $db, int $movieId): void
    {
        try {
            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            $photos = $bot->message()->photo;
            $fileId = end($photos)->file_id;

            Movie::update($db, $movieId, ['file_id' => $fileId]);

            $bot->sendPhoto(
                photo: $fileId,
                caption: "✅ Kino posteri muvaffaqiyatli yangilandi!",
                reply_markup: Keyboard::mainMenu($bot)
            );

            MovieService::showMovie($bot, $db, $movieId);

            State::clearAll($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::error("Kino posterini yangilashda xatolik", $e->getMessage()),
                reply_markup: Keyboard::mainMenu($bot)
            );
            State::clearAll($bot);
        }
    }

    private static function handleCategoryImageUpload(Nutgram $bot, PDO $db): void
    {
        $bot->sendMessage("⚠️ Bu funksiya hozircha mavjud emas.");
    }

    private static function handleCategoryImageEdit(Nutgram $bot, PDO $db, int $categoryId): void
    {
        $bot->sendMessage("⚠️ Bu funksiya hozircha mavjud emas.");
    }

    private static function handleVideoUpload(Nutgram $bot, PDO $db): void
    {
        try {
            $movieId = State::get($bot, 'movie_id');
            if (!$movieId) {
                $bot->sendMessage(text: "⚠️ Kino ID topilmadi.");
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            $video = $bot->message()->video;

            State::set($bot, 'file_id', $video->file_id);

            $videoTitle = State::get($bot, 'video_title');
            $videoPart = State::get($bot, 'video_part');

            if (empty($videoTitle)) {
                State::set($bot, 'state', 'add_video_title');

                $nextPart = Video::getNextPartNumber($db, (int)$movieId);

                $bot->sendMessage(
                    text: "🎬 Video qabul qilindi!\n\nEndi video sarlavhasini kiriting (masalan: {$nextPart}-qism):",
                    reply_markup: Keyboard::cancel()
                );
                return;
            }

            if (empty($videoPart)) {
                State::set($bot, 'state', 'add_video_part');

                $nextPart = Video::getNextPartNumber($db, (int)$movieId);

                $bot->sendMessage(
                    text: "🔢 Endi video qism raqamini kiriting (masalan: {$nextPart}):",
                    reply_markup: Keyboard::cancel()
                );
                return;
            }

            $videoData = [
                'movie_id' => (int)$movieId,
                'title' => $videoTitle,
                'part_number' => (int)$videoPart,
                'file_id' => $video->file_id
            ];

            $videoId = Video::create($db, $videoData);

            $message = "✅ Video muvaffaqiyatli qo'shildi!\n\n" .
                "🎬 <b>Kino:</b> {$movie['title']}\n" .
                "📹 <b>Video:</b> {$videoTitle}\n" .
                "🔢 <b>Qism:</b> {$videoPart}\n\n" .
                "Yana video qo'shish uchun video yuboring yoki bosh menyuga qaytish uchun /start buyrug'ini bosing.";

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );

            State::set($bot, 'state', 'add_video');
            State::clear($bot, ['video_title', 'video_part', 'file_id']);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Video qo'shishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::cancel()
            );
        }
    }

    private static function handleDocumentVideoUpload(Nutgram $bot, PDO $db): void
    {
        try {
            $movieId = State::get($bot, 'movie_id');
            if (!$movieId) {
                $bot->sendMessage(text: "⚠️ Kino ID topilmadi.");
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            $document = $bot->message()->document;

            State::set($bot, 'file_id', $document->file_id);

            $videoTitle = State::get($bot, 'video_title');
            $videoPart = State::get($bot, 'video_part');

            if (empty($videoTitle)) {
                State::set($bot, 'state', 'add_video_title');

                $nextPart = Video::getNextPartNumber($db, (int)$movieId);

                $bot->sendMessage(
                    text: "🎬 Video qabul qilindi!\n\nEndi video sarlavhasini kiriting (masalan: {$nextPart}-qism):",
                    reply_markup: Keyboard::cancel()
                );
                return;
            }

            if (empty($videoPart)) {
                State::set($bot, 'state', 'add_video_part');

                $nextPart = Video::getNextPartNumber($db, (int)$movieId);

                $bot->sendMessage(
                    text: "🔢 Endi video qism raqamini kiriting (masalan: {$nextPart}):",
                    reply_markup: Keyboard::cancel()
                );
                return;
            }

            $videoData = [
                'movie_id' => (int)$movieId,
                'title' => $videoTitle,
                'part_number' => (int)$videoPart,
                'file_id' => $document->file_id
            ];

            $videoId = Video::create($db, $videoData);

            $message = "✅ Video muvaffaqiyatli qo'shildi!\n\n" .
                "🎬 <b>Kino:</b> {$movie['title']}\n" .
                "📹 <b>Video:</b> {$videoTitle}\n" .
                "🔢 <b>Qism:</b> {$videoPart}\n\n" .
                "Yana video qo'shish uchun video yuboring yoki bosh menyuga qaytish uchun /start buyrug'ini bosing.";

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );

            State::set($bot, 'state', 'add_video');
            State::clear($bot, ['video_title', 'video_part', 'file_id']);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Video qo'shishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::cancel()
            );
        }
    }

    private static function handleVideoEdit(Nutgram $bot, PDO $db, int $videoId): void
    {
        try {
            $video = Video::findById($db, $videoId);
            if (!$video) {
                $bot->sendMessage(text: Text::videoNotFound());
                return;
            }

            $uploadedVideo = $bot->message()->video;

            Video::update($db, $videoId, [
                'file_id' => $uploadedVideo->file_id
            ]);

            $message = "✅ Video muvaffaqiyatli yangilandi!\n\n" .
                "🎬 <b>Kino:</b> {$video['movie_title']}\n" .
                "📹 <b>Video:</b> {$video['title']}\n" .
                "🔢 <b>Qism:</b> {$video['part_number']}";

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::mainMenu($bot)
            );

            VideoService::showVideos($bot, $db, $video['movie_id'], 1, true);

            State::clearAll($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::error("Videoni yangilashda xatolik", $e->getMessage()),
                reply_markup: Keyboard::mainMenu($bot)
            );
            State::clearAll($bot);
        }
    }

    private static function handleDocumentVideoEdit(Nutgram $bot, PDO $db, int $videoId): void
    {
        try {
            $video = Video::findById($db, $videoId);
            if (!$video) {
                $bot->sendMessage(text: Text::videoNotFound());
                return;
            }

            $document = $bot->message()->document;

            Video::update($db, $videoId, [
                'file_id' => $document->file_id
            ]);

            $message = "✅ Video muvaffaqiyatli yangilandi!\n\n" .
                "🎬 <b>Kino:</b> {$video['movie_title']}\n" .
                "📹 <b>Video:</b> {$video['title']}\n" .
                "🔢 <b>Qism:</b> {$video['part_number']}";

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::mainMenu($bot)
            );

            VideoService::showVideos($bot, $db, $video['movie_id'], 1, true);

            State::clearAll($bot);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::error("Videoni yangilashda xatolik", $e->getMessage()),
                reply_markup: Keyboard::mainMenu($bot)
            );
            State::clearAll($bot);
        }
    }
}
