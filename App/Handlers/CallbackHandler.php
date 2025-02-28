<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, StatisticsService, ChannelService, VideoService, BroadcastService};
use App\Models\{Movie, Video};
use App\Helpers\{Menu, Keyboard, Validator, Text, State, Config};
use PDO;

class CallbackHandler
{
    /**
     * Barcha callback handlerlari ro'yxatga olish
     * @param Nutgram $bot Telegram bot
     * @param PDO $db Database ulanish
     */
    public static function register(Nutgram $bot, PDO $db): void
    {
        // Asosiy CallbackQuery handlerlar
        self::registerMovieCallbacks($bot, $db);
        self::registerVideoCallbacks($bot, $db);
        self::registerAdminCallbacks($bot, $db);
        self::registerPaginationCallbacks($bot, $db);

        // Nomalum callbacklar uchun default handler
        $bot->onCallbackQuery(function (Nutgram $bot) {
            $bot->answerCallbackQuery(
                text: "Bu funksiya hozircha mavjud emas",
                show_alert: true
            );
        });
    }

    /**
     * Kino bilan bog'liq callbacklar
     * @param Nutgram $bot Telegram bot
     * @param PDO $db Database ulanish
     */
    private static function registerMovieCallbacks(Nutgram $bot, PDO $db): void
    {
        // Kino ma'lumotlarini ko'rsatish
        $bot->onCallbackQueryData('movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::findByID($db, (int)$movieId, $bot->userId());
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }
            MovieService::show($bot, $db, $movie);
            $bot->answerCallbackQuery();
        });

        // Kino videolarini ko'rish
        $bot->onCallbackQueryData('watch_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::findByID($db, (int)$movieId, $bot->userId());
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $totalVideos = Video::getCountByMovieID($db, $movie['id']);
            if ($totalVideos === 0) {
                $bot->answerCallbackQuery(text: "Bu kino uchun videolar mavjud emas", show_alert: true);
                return;
            }

            VideoService::showVideos($bot, $db, $movie['id']);
            $bot->answerCallbackQuery();
        });

        // Kinoni like/unlike qilish
        $bot->onCallbackQueryData('like_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::findByID($db, (int)$movieId, $bot->userId());
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            // Like statusini o'zgartirish
            $isLiked = Movie::toggleLike($db, $bot->userId(), $movie['id']);

            // Yangilangan ma'lumotlarni olish
            $movie = Movie::findByID($db, (int)$movieId, $bot->userId());

            // Tugmalarni yangilash
            $videos = Video::getAllByMovieID($db, $movie['id']);
            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::MovieActions($movie, ['video_count' => count($videos), 'is_liked' => $movie['is_liked']], Validator::isAdmin($bot))
            );

            // Like haqida xabar berish
            $bot->answerCallbackQuery(
                text: $isLiked ? "❤️ Sevimlilarga qo'shildi" : "🤍 Sevimlilardan olib tashlandi"
            );
        });

        // Ko'rishlar uchun (hech qanday amal yo'q)
        $bot->onCallbackQueryData('views_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $bot->answerCallbackQuery();
        });
    }

    /**
     * Video bilan bog'liq callbacklar
     * @param Nutgram $bot Telegram bot
     * @param PDO $db Database ulanish
     */
    private static function registerVideoCallbacks(Nutgram $bot, PDO $db): void
    {
        // Videoni ijro etish
        $bot->onCallbackQueryData('play_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            $video = Video::findByID($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(text: "Video topilmadi", show_alert: true);
                return;
            }

            VideoService::playVideo($bot, $db, (int)$videoId);
            $bot->answerCallbackQuery();
        });

        // Video qo'shish
        $bot->onCallbackQueryData('add_video_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            VideoService::startAddVideo($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        // Videoni tahrirlash
        $bot->onCallbackQueryData('edit_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            VideoService::startEditVideo($bot, $db, (int)$videoId);
            $bot->answerCallbackQuery();
        });

        // Videoni o'chirish
        $bot->onCallbackQueryData('delete_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $video = Video::findByID($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(text: "Video topilmadi", show_alert: true);
                return;
            }

            // O'chirish tasdig'i bilan tugmalarni ko'rsatish
            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::ConfirmDelete('video', $videoId)
            );
            $bot->answerCallbackQuery();
        });

        // Videoni o'chirishni tasdiqlash
        $bot->onCallbackQueryData('confirm_delete_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $video = Video::findByID($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(text: "Video topilmadi", show_alert: true);
                return;
            }

            $movieId = $video['movie_id'];
            VideoService::deleteVideo($bot, $db, (int)$videoId);
            $bot->answerCallbackQuery(text: "Video o'chirildi!");
        });

        // Videoni o'chirishni bekor qilish
        $bot->onCallbackQueryData('cancel_delete_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $video = Video::findByID($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(text: "Video topilmadi", show_alert: true);
                return;
            }

            // Videolar ro'yxatiga qaytish
            VideoService::showVideos($bot, $db, $video['movie_id']);
            $bot->answerCallbackQuery();
        });
    }

    /**
     * Admin uchun callbacklar
     * @param Nutgram $bot Telegram bot
     * @param PDO $db Database ulanish
     */
    private static function registerAdminCallbacks(Nutgram $bot, PDO $db): void
    {
        // Kinoni tahrirlash
        $bot->onCallbackQueryData('edit_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::MovieEditActions($movie['id'])
            );
            $bot->answerCallbackQuery();
        });

        // Kino sarlavhasini tahrirlash
        $bot->onCallbackQueryData('edit_movie_title_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            State::setState($bot, 'state', 'edit_movie_title_' . $movieId);
            $bot->sendMessage(
                text: "✏️ <b>Kino sarlavhasini tahrirlash</b>\n\n" .
                    "Joriy sarlavha: <b>{$movie['title']}</b>\n\n" .
                    "Yangi sarlavhani kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::Cancel()
            );
            $bot->answerCallbackQuery();
        });

        // Kino yilini tahrirlash
        $bot->onCallbackQueryData('edit_movie_year_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            State::setState($bot, 'state', 'edit_movie_year_' . $movieId);
            $bot->sendMessage(
                text: "✏️ <b>Kino yilini tahrirlash</b>\n\n" .
                    "Joriy yil: <b>{$movie['year']}</b>\n\n" .
                    "Yangi yilni kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::Cancel()
            );
            $bot->answerCallbackQuery();
        });

        // Kino tavsifini tahrirlash
        $bot->onCallbackQueryData('edit_movie_description_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            State::setState($bot, 'state', 'edit_movie_description_' . $movieId);
            $bot->sendMessage(
                text: "✏️ <b>Kino tavsifini tahrirlash</b>\n\n" .
                    "Joriy tavsif:\n<b>{$movie['description']}</b>\n\n" .
                    "Yangi tavsifni kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::Cancel()
            );
            $bot->answerCallbackQuery();
        });

        // Kino rasmini tahrirlash
        $bot->onCallbackQueryData('edit_movie_photo_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            State::setState($bot, 'state', 'edit_movie_photo_' . $movieId);
            $bot->sendMessage(
                text: "✏️ <b>Kino posterini tahrirlash</b>\n\n" .
                    "Yangi poster rasmini yuboring:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::Cancel()
            );
            $bot->answerCallbackQuery();
        });

        // Kinoni o'chirish
        $bot->onCallbackQueryData('delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            MovieService::confirmDeleteMovie($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('confirm_delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            try {
                Movie::delete($db, (int)$movieId);
                $bot->answerCallbackQuery(text: "Kino o'chirildi!", show_alert: true);

            } catch (\Exception $e) {
                $bot->answerCallbackQuery(text: "Xatolik: " . $e->getMessage(), show_alert: true);
            }
        });

        $bot->onCallbackQueryData('cancel_delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            MovieService::show($bot, $db, $movie);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('refresh_stats', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            StatisticsService::refresh($bot, $db);
        });

        $bot->onCallbackQueryData('manage_videos_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::findByID($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            State::setState($bot, 'movie_id', (string)$movie['id']);
            State::setState($bot, 'state', 'add_video');

            VideoService::showVideos($bot, $db, $movie['id']);
            $bot->answerCallbackQuery();
        });
    }

    /**
     * Sahifalash bilan bog'liq callbacklar.
     * @param Nutgram $bot Telegram bot.
     * @param PDO $db Database ulanish.
     */
    private static function registerPaginationCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('trending_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $page = (int)$page;
            MovieService::showTrending($bot, $db, $page);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('search_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $query = $bot->getUserData('last_search_query', '');
            if (empty($query)) {
                $bot->answerCallbackQuery(text: "Qidiruv so'rovi topilmadi", show_alert: true);
                return;
            }

            $page = (int)$page;
            $perPage = Config::getItemsPerPage();
            $offset = ($page - 1) * $perPage;

            $totalMovies = Movie::searchCount($db, $query);
            $movies = Movie::searchByText($db, $query, $bot->userId(), $perPage, $offset);
            $totalPages = ceil($totalMovies / $perPage);

            if (empty($movies)) {
                $bot->answerCallbackQuery(text: "Kinolar topilmadi", show_alert: true);
                return;
            }

            $message = "🔍 <b>Qidiruv natijalari:</b> \"{$query}\" (sahifa {$page}/{$totalPages})\n\n";
            $message .= "✅ <b>Topildi:</b> " . $totalMovies . " ta kino.\n";

            $movieButtons = [];
            foreach ($movies as $movie) {
                $movieButtons[] = [Keyboard::getCallbackButton($movie['title'], "movie_{$movie['id']}")];
            }

            $keyboard = Keyboard::Pagination('search', $page, $totalPages, $movieButtons);

            $bot->editMessageText(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('favorites_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $page = (int)$page;
            MovieService::showFavorites($bot, $db, $page);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('videos_([0-9]+)_page_([0-9]+)', function (Nutgram $bot, $movieId, $page) use ($db) {
            $movieId = (int)$movieId;
            $page = (int)$page;
            VideoService::showVideos($bot, $db, $movieId, $page);
            $bot->answerCallbackQuery();
        });
    }
}
