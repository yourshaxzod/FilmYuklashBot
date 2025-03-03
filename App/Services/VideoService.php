<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video};
use App\Helpers\{Formatter, Keyboard, Validator, Menu, State, Text, Config};
use PDO;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class VideoService
{
    /**
     * Kino uchun videolar ro'yxatini ko'rsatish.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param int $movieId Kino ID.
     * @param int $page Sahifa raqami.
     */
    public static function showVideos(Nutgram $bot, PDO $db, int $movieId, int $page = 1): void
    {
        try {
            $movie = Movie::findByID($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(
                    text: Text::MovieNotFound(),
                    reply_markup: Keyboard::MainMenu($bot)
                );
                return;
            }

            $perPage = Config::getItemsPerPage();
            $totalVideos = Video::getCountByMovieID($db, $movieId);

            if ($totalVideos === 0) {
                $message = "😞 <b>{$movie['title']}</b> uchun videolar mavjud emas.";

                if (Validator::isAdmin($bot)) {
                    $message .= "\n\nAdmin sifatida video qo'shishingiz mumkin!";

                    $bot->sendMessage(
                        text: $message,
                        parse_mode: ParseMode::HTML,
                        reply_markup: InlineKeyboardMarkup::make()
                            ->addRow(
                                InlineKeyboardButton::make(
                                    text: "➕ Video qo'shish",
                                    callback_data: "add_video_{$movieId}"
                                )
                            )
                            ->addRow(
                                InlineKeyboardButton::make(
                                    text: "🔙 Orqaga",
                                    callback_data: "movie_{$movieId}"
                                )
                            )
                    );
                } else {
                    $bot->sendMessage(
                        text: $message,
                        parse_mode: ParseMode::HTML,
                        reply_markup: InlineKeyboardMarkup::make()
                            ->addRow(
                                InlineKeyboardButton::make(
                                    text: "🔙 Orqaga",
                                    callback_data: "movie_{$movieId}"
                                )
                            )
                    );
                }
                return;
            }

            $offset = ($page - 1) * $perPage;
            $videos = Video::getAllByMovieID($db, $movieId, $perPage, $offset);
            $totalPages = ceil($totalVideos / $perPage);

            $message = "📹 <b>{$movie['title']}</b> videolari (sahifa {$page}/{$totalPages})\n\n";
            $message .= "Jami: " . $totalVideos . " ta video\n";
            $message .= "Kerakli qismni tanlang:";

            $videoButtons = [];
            foreach ($videos as $video) {
                $videoButtons[] = [
                    InlineKeyboardButton::make(
                        text: "📹 {$video['id']}-qism: {$video['title']}",
                        callback_data: "play_video_{$video['id']}"
                    )
                ];
            }

            if (Validator::isAdmin($bot)) {
                $videoButtons[] = [
                    InlineKeyboardButton::make(
                        text: "➕ Video qo'shish",
                        callback_data: "add_video_{$movieId}"
                    )
                ];
            }

            $videoButtons[] = [
                InlineKeyboardButton::make(
                    text: "🔙 Kinoga qaytish",
                    callback_data: "movie_{$movieId}"
                )
            ];

            $keyboard = Keyboard::Pagination("videos_{$movieId}", $page, $totalPages, $videoButtons);

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Videolarni ko'rsatishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    /**
     * Videoni yuborish va ijro etish.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param int $videoId Video ID.
     */
    public static function playVideo(Nutgram $bot, PDO $db, int $videoId): void
    {
        try {
            $video = Video::findByID($db, $videoId);
            if (!$video) {
                $bot->sendMessage(
                    text: Text::VideoNotFound(),
                    reply_markup: Keyboard::MainMenu($bot)
                );
                return;
            }

            $caption = "{$video['movie_title']} - {$video['title']} ({$video['id']}-qism)";

            $bot->sendVideo(
                video: $video['file_id'],
                caption: $caption,
                parse_mode: ParseMode::HTML,
                supports_streaming: true
            );

            Movie::addView($db, $bot->userId(), $video['movie_id']);

            $nextVideo = Video::findByID($db, $video['movie_id'], $video['id'] + 1);

            $keyboard = InlineKeyboardMarkup::make();

            if ($nextVideo) {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: "▶️ Keyingi qismni ko'rish",
                        callback_data: "play_video_{$nextVideo['id']}"
                    )
                );

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: "🔙 Kinoga qaytish",
                        callback_data: "movie_{$video['movie_id']}"
                    )
                );

                $bot->sendMessage(
                    text: "⏭ <b>Keyingi qism:</b> {$nextVideo['id']}-qism",
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            } else {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: "🔙 Kinoga qaytish",
                        callback_data: "movie_{$video['movie_id']}"
                    )
                );

                $bot->sendMessage(
                    text: "✅ <b>Bu kinoning so'nggi qismi.</b>",
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Videoni ijro etishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    /**
     * Video qo'shish jarayonini boshlash.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param int $movieId Kino ID.
     */
    public static function startAddVideo(Nutgram $bot, PDO $db, int $movieId): void
    {
        if (!Validator::isAdmin($bot)) {
            $bot->sendMessage(text: Text::NoPermission());
            return;
        }

        try {
            $movie = Movie::findByID($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::MovieNotFound());
                return;
            }

            State::set($bot, 'state', 'add_video');
            State::set($bot, 'movie_id', (string)$movieId);

            $nextId = Video::getNextPartNumber($db, $movieId);

            $message = "📹 <b>Video qo'shish</b>\n\n" .
                "🎬 <b>Kino:</b> {$movie['title']}\n" .
                "🔢 <b>Keyingi qism raqami:</b> {$nextId}\n\n" .
                "Qo'shmoqchi bo'lgan video sarlavhasini kiriting:";

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::Cancel()
            );

            State::set($bot, 'state', 'add_video_title');
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Video qo'shishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    /**
     * Videoni tahrirlash jarayonini boshlash.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param int $videoId Video ID.
     */
    public static function startEditVideo(Nutgram $bot, PDO $db, int $videoId): void
    {
        if (!Validator::isAdmin($bot)) {
            $bot->sendMessage(text: Text::NoPermission());
            return;
        }

        try {
            $video = Video::findByID($db, $videoId);
            if (!$video) {
                $bot->sendMessage(text: Text::VideoNotFound());
                return;
            }

            State::set($bot, 'state', "edit_video_{$videoId}");
            State::set($bot, 'movie_id', (string)$video['movie_id']);

            $message = "✏️ <b>Videoni tahrirlash</b>\n\n" .
                "🎬 <b>Kino:</b> {$video['movie_title']}\n" .
                "📹 <b>Video:</b> {$video['title']}\n" .
                "🔢 <b>Qism raqami:</b> {$video['id']}\n\n" .
                "Tahrirlash uchun yangi video yuboring.";

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::Cancel()
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Videoni tahrirlashda xatolik", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    /**
     * Videoni o'chirish.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param int $videoId Video ID.
     */
    public static function deleteVideo(Nutgram $bot, PDO $db, int $videoId): void
    {
        if (!Validator::isAdmin($bot)) {
            $bot->sendMessage(text: Text::NoPermission());
            return;
        }

        try {
            $video = Video::findByID($db, $videoId);
            if (!$video) {
                $bot->sendMessage(text: Text::VideoNotFound());
                return;
            }

            $movieId = $video['movie_id'];

            Video::delete($db, $videoId);

            $bot->sendMessage(
                text: "✅ Video muvaffaqiyatli o'chirildi!",
                reply_markup: Keyboard::MainMenu($bot)
            );

            self::showVideos($bot, $db, $movieId);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Videoni o'chirishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    /**
     * Video sarlavhasini qayta ishlash.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     * @param string $title Video sarlavhasi.
     */
    public static function processVideoTitle(Nutgram $bot, PDO $db, string $title): void
    {
        if (!Validator::isAdmin($bot)) {
            $bot->sendMessage(text: Text::NoPermission());
            return;
        }

        try {
            if (strlen(trim($title)) < 2) {
                $bot->sendMessage(
                    text: "⚠️ Video sarlavhasi kamida 2 ta belgi bo'lishi kerak.",
                    reply_markup: Keyboard::Cancel()
                );
                return;
            }

            State::set($bot, 'video_title', $title);

            $movieId = (int)$bot->getUserData('movie_id');

            if ($bot->getUserData('file_id')) {
                self::processStoredVideo($bot, $db);
                return;
            }

            $message = "✅ Sarlavha qabul qilindi.\n\n" .
                "Endi, iltimos, videoni yuboring.\n";

            $bot->sendMessage(
                text: $message,
                reply_markup: Keyboard::Cancel()
            );

            State::set($bot, 'state', 'add_video');
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Sarlavhani qayta ishlashda xatolik", $e->getMessage()),
                reply_markup: Keyboard::Cancel()
            );
        }
    }

    /**
     * Telegram orqali yuklangan videoni qayta ishlash.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     */
    public static function processUploadedVideo(Nutgram $bot, PDO $db): void
    {
        if (!Validator::isAdmin($bot)) {
            $bot->sendMessage(text: Text::NoPermission());
            return;
        }

        try {
            $movieId = (int)$bot->getUserData('movie_id');
            if (!$movieId) {
                $bot->sendMessage(text: "⚠️ Kino ID topilmadi.");
                return;
            }

            $movie = Movie::findByID($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::MovieNotFound());
                return;
            }

            $video = $bot->message()->video;

            $videoTitle = $bot->getUserData('video_title');

            if (empty($videoTitle)) {
                State::set($bot, 'state', 'add_video_title');
                State::set($bot, 'file_id', $video->file_id);

                if (isset($video->duration)) {
                    State::set($bot, 'video_duration', (string)$video->duration);
                }

                if (isset($video->file_size)) {
                    State::set($bot, 'video_file_size', (string)$video->file_size);
                }

                $bot->sendMessage(
                    text: "🎬 Video qabul qilindi!\n\nEndi, iltimos, video sarlavhasini kiriting:",
                    reply_markup: Keyboard::Cancel()
                );
                return;
            }

            self::processStoredVideo($bot, $db);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Videoni qayta ishlashda xatolik", $e->getMessage()),
                reply_markup: Keyboard::Cancel()
            );
        }
    }

    /**
     * Saqlangan video ma'lumotlari bilan video qo'shish.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     */
    private static function processStoredVideo(Nutgram $bot, PDO $db): void
    {
        try {
            $movieId = (int)$bot->getUserData('movie_id');
            $movie = Movie::findByID($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::MovieNotFound());
                return;
            }

            $videoTitle = $bot->getUserData('video_title');
            $fileId = $bot->getUserData('file_id');

            if (empty($videoTitle) || empty($fileId)) {
                $bot->sendMessage(text: "⚠️ Video ma'lumotlari to'liq emas.");
                return;
            }

            $videoData = [
                'movie_id' => $movieId,
                'title' => $videoTitle,
                'file_id' => $fileId
            ];

            // Videoni bazaga qo'shish
            $videoId = Video::create($db, $videoData);

            // Formatlanishi kerak bo'lgan ma'lumotlar
            $formattedSize = '';
            $formattedDuration = '';

            // Qo'shimcha ma'lumotlarni formatlash
            if ($fileSize = $bot->getUserData('video_file_size')) {
                $formattedSize = "\n📦 <b>Hajmi:</b> " . Formatter::formatFileSize((int)$fileSize);
            }

            if ($duration = $bot->getUserData('video_duration')) {
                $formattedDuration = "\n⏱ <b>Davomiyligi:</b> " . Formatter::formatDuration((int)$duration);
            }

            // Muvaffaqiyat haqida xabar berish
            $message = "✅ Video muvaffaqiyatli qo'shildi!\n\n" .
                "🎬 <b>Kino:</b> {$movie['title']}\n" .
                "📹 <b>Video:</b> {$videoTitle}\n" .
                "🔢 <b>Qism:</b> {$videoId}" .
                $formattedDuration .
                $formattedSize .
                "\n\nYana video qo'shish uchun video yuboring yoki bosh menyuga qaytish uchun /start buyrug'ini bosing.";

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::MainMenu($bot)
            );

            State::set($bot, 'state', 'add_video');
            State::set($bot, 'video_title', null);
            State::set($bot, 'file_id', null);
            State::set($bot, 'video_duration', null);
            State::set($bot, 'video_file_size', null);
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: Text::Error("Video qo'shishda xatolik", $e->getMessage()),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }
}
