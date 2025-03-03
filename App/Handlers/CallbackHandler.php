<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, VideoService, StatisticsService, CategoryService, TavsiyaService};
use App\Models\{Movie, Video, Category, Channel};
use App\Helpers\{Menu, Keyboard, Validator, State};
use PDO;

class CallbackHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        self::registerMovieCallbacks($bot, $db);
        self::registerVideoCallbacks($bot, $db);
        self::registerCategoryCallbacks($bot, $db);
        self::registerChannelCallbacks($bot, $db);
        self::registerAdminCallbacks($bot, $db);
        self::registerPaginationCallbacks($bot, $db);

        $bot->onCallbackQuery(function (Nutgram $bot) {
            if ($bot->callbackQuery()->data === 'noop') {
                $bot->answerCallbackQuery();
                return;
            }

            $bot->answerCallbackQuery(
                text: "Bu funksiya hozircha mavjud emas",
                show_alert: true
            );
        });
    }

    private static function registerMovieCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::findById($db, (int)$movieId, $bot->userId());
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            MovieService::showMovie($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('watch_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $totalVideos = Video::getCountByMovieId($db, (int)$movieId);
            if ($totalVideos === 0) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Bu kino uchun videolar mavjud emas",
                    show_alert: true
                );
                return;
            }

            VideoService::showVideos($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('like_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {

            $isLiked = Movie::toggleLike($db, $bot->userId(), (int)$movieId);

            if ($isLiked) {
                TavsiyaService::updateUserInterests($db, $bot->userId(), (int)$movieId, 'like');
            }

            $movie = Movie::findById($db, (int)$movieId, $bot->userId());

            $categories = Category::getByMovieId($db, (int)$movieId);
            $totalVideos = Video::getCountByMovieId($db, (int)$movieId);

            $extras = [
                'video_count' => $totalVideos,
                'is_liked' => $movie['is_liked'],
                'categories' => $categories
            ];

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::movieActions($movie, $extras, Validator::isAdmin($bot))
            );

            $bot->answerCallbackQuery(
                text: $isLiked ? "❤️ Sevimlilarga qo'shildi" : "🤍 Sevimlilardan olib tashlandi"
            );
        });

        $bot->onCallbackQueryData('similar_movies_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $similarMovies = TavsiyaService::getSimilarMovies($db, (int)$movieId);

            if (count($similarMovies) === 0) {
                $bot->answerCallbackQuery(
                    text: "⚠️ O'xshash kinolar topilmadi",
                    show_alert: true
                );
                return;
            }

            $message = "🎬 <b>O'xshash kinolar</b>\n\n";
            $message .= "Quyidagilardan birini tanlang:";

            $movieButtons = [];
            foreach ($similarMovies as $movie) {
                $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")];
            }

            $movieButtons[] = [Keyboard::getCallbackButton("🔙 Orqaga", "movie_{$movieId}")];

            $bot->editMessageText(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::getInlineKeyboard($movieButtons)
            );

            $bot->answerCallbackQuery();
        });
    }

    private static function registerVideoCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('play_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            $video = Video::findById($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Video topilmadi",
                    show_alert: true
                );
                return;
            }

            VideoService::playVideo($bot, $db, (int)$videoId);

            Movie::addView($db, $bot->userId(), $video['movie_id']);
            TavsiyaService::updateUserInterests($db, $bot->userId(), $video['movie_id'], 'view');

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('add_video_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            VideoService::startAddVideo($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('edit_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $video = Video::findById($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Video topilmadi",
                    show_alert: true
                );
                return;
            }

            VideoService::startEditVideo($bot, $db, (int)$videoId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('delete_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $video = Video::findById($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Video topilmadi",
                    show_alert: true
                );
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::confirmDelete('video', $videoId)
            );
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('confirm_delete_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $video = Video::findById($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Video topilmadi",
                    show_alert: true
                );
                return;
            }

            $movieId = $video['movie_id'];

            VideoService::deleteVideo($bot, $db, (int)$videoId);
            $bot->answerCallbackQuery(text: "✅ Video o'chirildi!");
        });

        $bot->onCallbackQueryData('cancel_delete_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            $video = Video::findById($db, (int)$videoId);
            if (!$video) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Video topilmadi",
                    show_alert: true
                );
                return;
            }

            VideoService::showVideos($bot, $db, $video['movie_id']);
            $bot->answerCallbackQuery();
        });
    }

    private static function registerCategoryCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('categories', function (Nutgram $bot) use ($db) {

            CategoryService::showCategoryList($bot, $db);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('category_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
            CategoryService::showCategoryMovies($bot, $db, (int)$categoryId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('delete_category_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $category = Category::findById($db, (int)$categoryId);
            if (!$category) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kategoriya topilmadi",
                    show_alert: true
                );
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::confirmDelete('category', $categoryId)
            );
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('confirm_delete_category_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            try {
                Category::delete($db, (int)$categoryId);
                $bot->answerCallbackQuery(text: "✅ Kategoriya o'chirildi!");
                CategoryService::showCategoryList($bot, $db, true);
            } catch (\Exception $e) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Xatolik: " . $e->getMessage(),
                    show_alert: true
                );
            }
        });

        $bot->onCallbackQueryData('cancel_delete_category', function (Nutgram $bot) use ($db) {
            CategoryService::showCategoryList($bot, $db, true);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('edit_category_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $category = Category::findById($db, (int)$categoryId);
            if (!$category) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kategoriya topilmadi",
                    show_alert: true
                );
                return;
            }

            CategoryService::startEditCategory($bot, $db, (int)$categoryId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('manage_movie_categories_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            CategoryService::showCategorySelector($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('select_category_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $category = Category::findById($db, (int)$categoryId);
            if (!$category) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kategoriya topilmadi",
                    show_alert: true
                );
                return;
            }

            $selectedCategories = State::get($bot, 'selected_categories') ?? [];

            if (in_array($categoryId, $selectedCategories)) {
                $selectedCategories = array_diff($selectedCategories, [$categoryId]);
            } else {
                $selectedCategories[] = $categoryId;
            }

            State::set($bot, 'selected_categories', $selectedCategories);

            $movieId = State::get($bot, 'editing_movie_id');
            CategoryService::updateCategorySelector($bot, $db, (int)$movieId, $selectedCategories);

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('save_categories', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movieId = State::get($bot, 'editing_movie_id');
            $selectedCategories = State::get($bot, 'selected_categories') ?? [];

            try {
                Category::saveMovieCategories($db, (int)$movieId, $selectedCategories);

                $movie = Movie::findById($db, (int)$movieId, $bot->userId());
                MovieService::showMovie($bot, $db, (int)$movieId);

                $bot->answerCallbackQuery(text: "✅ Kategoriyalar saqlandi!");

                State::clear($bot, ['editing_movie_id', 'selected_categories']);
            } catch (\Exception $e) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Xatolik: " . $e->getMessage(),
                    show_alert: true
                );
            }
        });

        $bot->onCallbackQueryData('cancel_select_categories', function (Nutgram $bot) use ($db) {
            $movieId = State::get($bot, 'editing_movie_id');

            MovieService::showMovie($bot, $db, (int)$movieId);

            $bot->answerCallbackQuery(text: "❌ Kategoriyalar o'zgartirilmadi");

            State::clear($bot, ['editing_movie_id', 'selected_categories']);
        });
    }

    private static function registerChannelCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('add_channel', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'state', 'add_channel');
            Menu::showAddChannelGuide($bot);

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('delete_channel', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'state', 'delete_channel');

            $bot->sendMessage(
                text: "🔐 <b>Kanalni o'chirish</b>\n\nO'chirmoqchi bo'lgan kanal ID raqamini kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('check_subscription', function (Nutgram $bot) use ($db) {
            $notSubscribed = Channel::checkUserSubscription($bot, $db, $bot->userId());

            if (empty($notSubscribed)) {
                $bot->answerCallbackQuery(
                    text: "✅ Barcha kanallarga obuna bo'lgansiz!",
                    show_alert: true
                );

                Menu::showMainMenu($bot);
            } else {
                $bot->answerCallbackQuery(
                    text: "❌ Siz hali barcha kanallarga obuna bo'lmagansiz!",
                    show_alert: true
                );
            }
        });
    }

    private static function registerAdminCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('edit_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::movieEditActions((int)$movieId)
            );
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('edit_movie_title_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'state', "edit_movie_title_{$movieId}");

            $bot->sendMessage(
                text: "✏️ <b>Kino sarlavhasini tahrirlash</b>\n\n" .
                    "Joriy sarlavha: <b>{$movie['title']}</b>\n\n" .
                    "Yangi sarlavhani kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('edit_movie_year_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'state', "edit_movie_year_{$movieId}");

            $bot->sendMessage(
                text: "✏️ <b>Kino yilini tahrirlash</b>\n\n" .
                    "Joriy yil: <b>{$movie['year']}</b>\n\n" .
                    "Yangi yilni kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('edit_movie_description_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'state', "edit_movie_description_{$movieId}");

            $bot->sendMessage(
                text: "✏️ <b>Kino tavsifini tahrirlash</b>\n\n" .
                    "Joriy tavsif:\n<b>{$movie['description']}</b>\n\n" .
                    "Yangi tavsifni kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('edit_movie_photo_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'state', "edit_movie_photo_{$movieId}");

            $bot->sendMessage(
                text: "✏️ <b>Kino posterini tahrirlash</b>\n\n" .
                    "Yangi poster rasmini yuboring:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            $bot->editMessageReplyMarkup(
                reply_markup: Keyboard::confirmDelete('movie', $movieId)
            );

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('confirm_delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            try {
                Movie::delete($db, (int)$movieId);

                $bot->editMessageText(
                    text: "✅ <b>Kino muvaffaqiyatli o'chirildi!</b>",
                    parse_mode: 'HTML',
                    reply_markup: null
                );

                $bot->answerCallbackQuery(text: "✅ Kino o'chirildi!");
            } catch (\Exception $e) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Xatolik: " . $e->getMessage(),
                    show_alert: true
                );
            }
        });

        $bot->onCallbackQueryData('cancel_delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $movie = Movie::findById($db, (int)$movieId, $bot->userId());
            if ($movie) {
                MovieService::showMovie($bot, $db, $movie);
            }

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('refresh_stats', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            StatisticsService::refresh($bot, $db);
        });

        $bot->onCallbackQueryData('manage_videos_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            $movie = Movie::findById($db, (int)$movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Kino topilmadi",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'movie_id', (string)$movieId);

            VideoService::showVideos($bot, $db, (int)$movieId, 1, true);

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('back_to_main', function (Nutgram $bot) {
            State::clearAll($bot);

            Menu::showMainMenu($bot);

            $bot->answerCallbackQuery();
        });
    }

    private static function registerPaginationCallbacks(Nutgram $bot, PDO $db): void
    {
        $bot->onCallbackQueryData('trending_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $page = (int)$page;

            Menu::showTrendingMenu($bot, $db, $page);
            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('search_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $query = State::get($bot, 'last_search_query');
            if (empty($query)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Qidiruv so'rovi topilmadi",
                    show_alert: true
                );
                return;
            }

            $page = (int)$page;

            MovieService::search($bot, $db, $query, $page);

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('favorites_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $page = (int)$page;

            Menu::showFavoriteMenu($bot, $db, $page);

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('videos_([0-9]+)_page_([0-9]+)', function (Nutgram $bot, $movieId, $page) use ($db) {
            $movieId = (int)$movieId;
            $page = (int)$page;

            VideoService::showVideos($bot, $db, $movieId, $page, Validator::isAdmin($bot));

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('category_([0-9]+)_page_([0-9]+)', function (Nutgram $bot, $categoryId, $page) use ($db) {
            $categoryId = (int)$categoryId;
            $page = (int)$page;

            Menu::showCategoryMoviesMenu($bot, $db, $categoryId, $page);

            $bot->answerCallbackQuery();
        });

        $bot->onCallbackQueryData('recommendations_page_([0-9]+)', function (Nutgram $bot, $page) use ($db) {
            $page = (int)$page;

            Menu::showRecommendationsMenu($bot, $db, $page);

            $bot->answerCallbackQuery();
        });
    }
}
