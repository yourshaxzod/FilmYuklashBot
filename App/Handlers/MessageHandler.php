<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, ChannelService, StatisticsService};
use App\Models\{Movie, Video};
use App\Helpers\{Menu, State, Validator, Formatter, Keyboard};
use PDO;

class MessageHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onMessage(function (Nutgram $bot) use ($db) {
            $message = $bot->message();

            if (!$message->text) {
                return;
            }

            $state = State::getState($bot);
            if (!$state) {
                return;
            }

            if ($message->text === "↩️ Ortga qaytish") {
                State::clearState($bot);
                Menu::showMainMenu($bot);
                return;
            }

            if (self::handleAdminStates($bot, $db, $state, $message->text)) {
                return;
            }

            if (self::handleMovieStates($bot, $db, $state, $message->text)) {
                return;
            }

            if (self::handleVideoStates($bot, $db, $state, $message->text)) {
                return;
            }

            if (self::handleChannelStates($bot, $db, $state, $message->text)) {
                return;
            }
        });
    }

    private static function handleMovieStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        switch ($state) {
            case 'search':
                MovieService::search($bot, $db, $text);
                return true;

            case 'add_movie_title':
                if (!Validator::validateMovieTitle($text)) {
                    $bot->sendMessage("⚠️ Noto'g'ri kino nomi! Kamida 2 ta, ko'pi bilan 255 ta belgi bo'lishi kerak.");
                    return true;
                }

                State::setState($bot, 'movie_title', $text);
                State::setState($bot, 'state', 'add_movie_year');

                $bot->sendMessage("📅 Endi kino yilini kiriting (masalan: 2023):");
                return true;

            case 'add_movie_year':
                $year = (int)$text;

                if (!Validator::validateMovieYear($year)) {
                    $bot->sendMessage("⚠️ Noto'g'ri yil! 1900 dan hozirgi yilgacha bo'lgan son kiriting:");
                    return true;
                }

                State::setState($bot, 'movie_year', $year);
                State::setState($bot, 'state', 'add_movie_description');

                $bot->sendMessage("📝 Endi kino haqida tavsif kiriting:");
                return true;

            case 'add_movie_description':
                if (!Validator::validateMovieDescription($text)) {
                    $bot->sendMessage("⚠️ Noto'g'ri tavsif! Kamida 10 ta, ko'pi bilan 1000 ta belgi bo'lishi kerak.");
                    return true;
                }

                State::setState($bot, 'movie_description', $text);
                State::setState($bot, 'state', 'add_movie_photo');

                $bot->sendMessage("🖼 Endi kino posterini (rasm) yuboring:");
                return true;

            case 'add_movie_confirm':
                if ($text === "✅ Tasdiqlash") {
                    try {
                        $movieData = [
                            'title' => $bot->getUserData('movie_title'),
                            'description' => $bot->getUserData('movie_description'),
                            'year' => $bot->getUserData('movie_year'),
                            'file_id' => $bot->getUserData('movie_photo')
                        ];

                        $movieId = Movie::create($db, $movieData);

                        $bot->sendMessage(
                            text: "✅ Kino muvaffaqiyatli qo'shildi! ID: {$movieId}\n\nEndi kinoga video qo'shish uchun video yuboring:",
                            reply_markup: Keyboard::MainMenu($bot)
                        );

                        State::setState($bot, 'state', 'add_video');
                        State::setState($bot, 'movie_id', (string)$movieId);
                    } catch (\Exception $e) {
                        $bot->sendMessage(
                            text: "⚠️ Kino qo'shishda xatolik: " . $e->getMessage(),
                            reply_markup: Keyboard::MainMenu($bot)
                        );
                        State::clearState($bot);
                    }
                } else if ($text === "🚫 Bekor qilish") {
                    $bot->sendMessage(
                        text: "❌ Kino qo'shish bekor qilindi.",
                        reply_markup: Keyboard::MainMenu($bot)
                    );
                    State::clearState($bot);
                }
                return true;

            case 'edit_movie_id':
                if (!is_numeric($text)) {
                    $bot->sendMessage("⚠️ Kino ID raqam bo'lishi kerak!");
                    return true;
                }

                $movieId = (int)$text;
                $movie = Movie::find($db, $movieId);

                if (!$movie) {
                    $bot->sendMessage("⚠️ Bu ID bilan kino topilmadi!");
                    return true;
                }

                State::setState($bot, 'edit_movie_id', (string)$movieId);

                $bot->sendPhoto(
                    photo: $movie['file_id'] ?? 'https://via.placeholder.com/400x600?text=No+Image',
                    caption: "🎬 <b>" . $movie['title'] . "</b>\n\n" .
                        "🆔 <b>ID:</b> " . $movie['id'] . "\n" .
                        "📝 <b>Tavsif:</b> " . $movie['description'] . "\n" .
                        "📅 <b>Yil:</b> " . $movie['year'] . "\n\n" .
                        "Nimani tahrirlash kerak?",
                    parse_mode: 'HTML',
                    reply_markup: Keyboard::MovieEditActions($movieId)
                );

                return true;
        }

        return false;
    }

    private static function handleVideoStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        switch ($state) {
            case 'add_video_title':
                if (!Validator::validateVideoTitle($text)) {
                    $bot->sendMessage("⚠️ Noto'g'ri video sarlavhasi! Kamida 2 ta, ko'pi bilan 255 ta belgi bo'lishi kerak.");
                    return true;
                }

                State::setState($bot, 'video_title', $text);
                State::setState($bot, 'state', 'add_video_part');

                $movieId = $bot->getUserData('movie_id');
                $nextPart = Video::getNextPartNumber($db, (int)$movieId);

                $bot->sendMessage("🔢 Endi video qism raqamini kiriting (masalan: {$nextPart}):");
                return true;

            case 'add_video_part':
                if (!Validator::validateVideoPartNumber($text)) {
                    $bot->sendMessage("⚠️ Noto'g'ri qism raqami! 1 dan 1000 gacha son bo'lishi kerak.");
                    return true;
                }

                $partNumber = (int)$text;
                $movieId = $bot->getUserData('movie_id');

                try {
                    $existing = Video::findByPart($db, (int)$movieId, $partNumber);
                    if ($existing) {
                        $bot->sendMessage("⚠️ Bu qism raqami allaqachon mavjud. Boshqa raqam kiriting:");
                        return true;
                    }
                } catch (\Exception $e) {
                }

                State::setState($bot, 'video_part', (string)$partNumber);

                $videoFileId = $bot->getUserData('file_id');
                $videoDuration = $bot->getUserData('video_duration');
                $videoFileSize = $bot->getUserData('video_file_size');
                $videoTitle = $bot->getUserData('video_title');

                if ($videoFileId) {
                    try {
                        $videoData = [
                            'movie_id' => (int)$movieId,
                            'title' => $videoTitle,
                            'part_number' => $partNumber,
                            'file_id' => $videoFileId,
                            'duration' => $videoDuration,
                            'file_size' => $videoFileSize
                        ];

                        $videoId = Video::create($db, $videoData);

                        $formattedSize = Formatter::formatFileSize($videoFileSize);
                        $formattedDuration = Formatter::formatDuration($videoDuration);

                        $movie = Movie::find($db, (int)$movieId);

                        $bot->sendMessage(
                            text: "✅ Video muvaffaqiyatli qo'shildi!\n\n" .
                                "🎬 <b>Kino:</b> {$movie['title']}\n" .
                                "📹 <b>Video:</b> {$videoTitle}\n" .
                                "🔢 <b>Qism:</b> {$partNumber}\n" .
                                "⏱ <b>Davomiyligi:</b> {$formattedDuration}\n" .
                                "📦 <b>Hajmi:</b> {$formattedSize}\n\n" .
                                "Yana video qo'shish uchun video yuboring yoki bosh menyuga qaytish uchun /start buyrug'ini bosing.",
                            parse_mode: 'HTML',
                            reply_markup: Keyboard::MainMenu($bot)
                        );

                        State::setState($bot, 'state', 'add_video');
                        State::setState($bot, 'video_title', null);
                        State::setState($bot, 'video_part', null);
                        State::setState($bot, 'file_id', null);
                        State::setState($bot, 'video_duration', null);
                        State::setState($bot, 'video_file_size', null);
                    } catch (\Exception $e) {
                        $bot->sendMessage(
                            text: "⚠️ Video qo'shishda xatolik: " . $e->getMessage(),
                            reply_markup: Keyboard::Cancel()
                        );
                    }
                } else {
                    $bot->sendMessage("📹 Endi videoni yuboring:");
                    State::setState($bot, 'state', 'add_video');
                }

                return true;
        }

        return false;
    }

    private static function handleAdminStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        if (!Validator::isAdmin($bot)) {
            return false;
        }

        switch ($state) {
            case 'panel':
                switch ($text) {
                    case '🎬 Kinolar':
                        Menu::showMovieManageMenu($bot);
                        return true;

                    case '🔐 Kanallar':
                        ChannelService::showChannels($bot, $db);
                        return true;

                    case '📊 Statistika':
                        StatisticsService::showStats($bot, $db);
                        return true;

                    case '📬 Habarlar':
                        State::setState($bot, 'state', 'broadcast_message');
                        $bot->sendMessage(
                            text: "📬 <b>Foydalanuvchilarga xabar yuborish</b>\n\n" .
                                "Yubormoqchi bo'lgan xabarni kiriting:",
                            parse_mode: 'HTML',
                            reply_markup: Keyboard::Cancel()
                        );
                        return true;
                }
                break;

            case 'broadcast_message':
                if ($text === "🚫 Bekor qilish") {
                    Menu::showAdminMenu($bot);
                    return true;
                }

                State::setState($bot, 'broadcast_text', $text);
                State::setState($bot, 'state', 'broadcast_confirm');

                $bot->sendMessage(
                    text: "📬 <b>Quyidagi xabarni yuborishni tasdiqlaysizmi?</b>\n\n" . $text,
                    parse_mode: 'HTML',
                    reply_markup: Keyboard::Confirm()
                );
                return true;

            case 'broadcast_confirm':
                if ($text === "✅ Tasdiqlash") {
                    $broadcastText = $bot->getUserData('broadcast_text');

                    if (empty($broadcastText)) {
                        $bot->sendMessage("⚠️ Xabar topilmadi!");
                        Menu::showAdminMenu($bot);
                        return true;
                    }

                    try {
                        $stmt = $db->query("SELECT user_id FROM users WHERE status = 'active'");
                        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        $bot->sendMessage(
                            text: "📬 <b>Xabar yuborilmoqda...</b>\n\n" .
                                "Jami foydalanuvchilar: " . count($users),
                            parse_mode: 'HTML'
                        );

                        $sent = 0;
                        $failed = 0;

                        foreach ($users as $userId) {
                            try {
                                $bot->sendMessage(
                                    chat_id: $userId,
                                    text: $broadcastText,
                                    parse_mode: 'HTML'
                                );
                                $sent++;
                            } catch (\Exception $e) {
                                $failed++;
                            }

                            usleep(50000); // 0.05 seconds
                        }

                        $bot->sendMessage(
                            text: "✅ <b>Xabar yuborildi!</b>\n\n" .
                                "✅ Yuborildi: {$sent}\n" .
                                "❌ Yuborilmadi: {$failed}",
                            parse_mode: 'HTML',
                            reply_markup: Keyboard::MainMenu($bot)
                        );
                    } catch (\Exception $e) {
                        $bot->sendMessage(
                            text: "⚠️ Xabar yuborishda xatolik: " . $e->getMessage(),
                            reply_markup: Keyboard::MainMenu($bot)
                        );
                    }

                    State::clearState($bot);
                } else if ($text === "🚫 Bekor qilish") {
                    $bot->sendMessage(
                        text: "❌ Xabar yuborish bekor qilindi.",
                        reply_markup: Keyboard::MainMenu($bot)
                    );
                    State::clearState($bot);
                }
                return true;
        }

        return false;
    }

    private static function handleChannelStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        if (!Validator::isAdmin($bot)) {
            return false;
        }

        switch ($state) {
            case 'add_channel':
                if ($text === "🚫 Bekor qilish") {
                    Menu::showAdminMenu($bot);
                    return true;
                }

                ChannelService::addChannel($bot, $db, $text);
                return true;

            case 'delete_channel':
                if ($text === "🚫 Bekor qilish") {
                    Menu::showAdminMenu($bot);
                    return true;
                }

                if (!is_numeric($text)) {
                    $bot->sendMessage("⚠️ Kanal ID raqam bo'lishi kerak!");
                    return true;
                }

                ChannelService::deleteChannel($bot, $db, (int)$text);
                return true;
        }

        return false;
    }
}
