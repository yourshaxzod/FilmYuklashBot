<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, StatisticsService};
use App\Models\{Movie, Video};
use App\Helpers\{Menu, Keyboard, Validator, Text, State};
use PDO;

class CallbackHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::find($db, (int)$movieId, $bot->userId());
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }
            MovieService::show($bot, $db, $movie);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('watch_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::find($db, (int)$movieId, $bot->userId());
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $videos = Video::getAllByMovie($db, $movie['id']);
            if (empty($videos)) {
                $bot->answerCallbackQuery(text: "Video topilmadi", show_alert: true);
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::VideoList($videos, $movie['id'])
            );
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('like_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::find($db, (int)$movieId, $bot->userId());
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $isLiked = Movie::toggleLike($db, $bot->userId(), $movie['id']);
            $movie = Movie::find($db, (int)$movieId, $bot->userId());

            $videos = Video::getAllByMovie($db, $movie['id']);
            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::MovieActions($movie, $videos, Validator::isAdmin($bot))
            );

            $bot->answerCallbackQuery(
                text: $isLiked ? "❤️ Sevimlilarga qo'shildi" : "🤍 Sevimlilardan olib tashlandi"
            );
        });

        $bot->onCallbackQueryData('play_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            $video = Video::find($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(text: "Video topilmadi", show_alert: true);
                return;
            }

            $bot->sendVideo(
                video: $video['video_file_id'],
                caption: "{$video['movie_title']} - {$video['title']} ({$video['part_number']}-qism)",
                supports_streaming: true
            );

            Movie::addView($db, $bot->userId(), $video['movie_id']);

            $bot->answerCallbackQuery();
        });

        self::registerAdminCallbacks($bot, $db);

        self::registerPaginationCallbacks($bot, $db);

        $bot->onCallbackQuery(function (Nutgram $bot) {
            $bot->answerCallbackQuery(
                text: "Bu funksiya hozircha mavjud emas",
                show_alert: true
            );
        });
    }

    private static function registerAdminCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('edit_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::find($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::MovieEditActions($movie['id'])
            );
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Sizda yetarli huquq yo'q", show_alert: true);
                return;
            }

            $movie = Movie::find($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::ConfirmDelete('movie', $movie['id'])
            );
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

            $movie = Movie::find($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $videos = Video::getAllByMovie($db, $movie['id']);
            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::MovieActions($movie, $videos, true)
            );
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

            $movie = Movie::find($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "Kino topilmadi", show_alert: true);
                return;
            }

            $videos = Video::getAllByMovie($db, $movie['id']);

            State::setState($bot, 'movie_id', (string)$movie['id']);

            $text = "🎬 <b>{$movie['title']}</b> videolari:\n\n";

            if (empty($videos)) {
                $text .= "Videolar mavjud emas. Qo'shish uchun video yuboring.";
            } else {
                $text .= "Jami: " . count($videos) . " ta video";
            }

            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: empty($videos) ? null : Keyboard::VideoList($videos, $movie['id'])
            );

            $bot->answerCallbackQuery();
        });
    }

    private static function registerPaginationCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('trending_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $page = (int)$page;
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $trending = Movie::getTrending($db, $perPage, $bot->userId(), $offset);
            $totalMovies = Movie::getCount($db);
            $totalPages = ceil($totalMovies / $perPage);

            if (empty($trending)) {
                $bot->answerCallbackQuery(text: "Kinolar topilmadi", show_alert: true);
                return;
            }

            $text = "🔥 <b>Trend kinolar</b> (sahifa {$page}/{$totalPages})";

            $keyboard = Keyboard::Pagination('trending', $page, $totalPages);

            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('search_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $query = $bot->getUserData('last_search_query', '');
            if (empty($query)) {
                $bot->answerCallbackQuery(text: "Qidiruv so'rovi topilmadi", show_alert: true);
                return;
            }

            $page = (int)$page;
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $movies = Movie::search($db, $query, $bot->userId(), $perPage, $offset);
            $totalMovies = count(Movie::search($db, $query, $bot->userId(), 1000));
            $totalPages = ceil($totalMovies / $perPage);

            if (empty($movies)) {
                $bot->answerCallbackQuery(text: "Kinolar topilmadi", show_alert: true);
                return;
            }

            $text = "🔍 <b>Qidiruv natijalari:</b> \"{$query}\" (sahifa {$page}/{$totalPages})";

            $keyboard = Keyboard::Pagination('search', $page, $totalPages);

            $bot->editMessageText(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );

            $bot->answerCallbackQuery();
        });
    }
}