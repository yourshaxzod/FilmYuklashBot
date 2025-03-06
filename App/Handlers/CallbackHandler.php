<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, VideoService, CategoryService, ChannelService, StatisticsService};
use App\Models\{Movie, Video, Category, Channel};
use App\Helpers\{Menu, Keyboard, Validator, State, Text};
use PDO;

class CallbackHandler
{
    /**
     * Register callback handlers
     */
    public static function register(Nutgram $bot, PDO $db): void
    {
        self::registerMovieCallbacks($bot, $db);
        self::registerVideoCallbacks($bot, $db);
        self::registerCategoryCallbacks($bot, $db);
        self::registerChannelCallbacks($bot, $db);
        self::registerAdminCallbacks($bot, $db);

        // Default callback handler
        $bot->onCallbackQuery(function (Nutgram $bot) {
            if ($bot->callbackQuery()->data === 'noop') {
                $bot->answerCallbackQuery();
                return;
            }

            if ($bot->callbackQuery()->data === 'back_to_main') {
                Menu::showMainMenu($bot);
                $bot->answerCallbackQuery();
                return;
            }

            $bot->answerCallbackQuery(
                text: "Bu funksiya hozircha mavjud emas",
                show_alert: true
            );
        });
    }

    /**
     * Register movie-related callbacks
     */
    private static function registerMovieCallbacks(Nutgram $bot, PDO $db): void
    {
        // Show movie details
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

        // Watch movie (show video list)
        $bot->onCallbackQueryData('watch_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            $totalVideos = Video::getCountByMovieId($db, (int)$movieId);
            if ($totalVideos === 0) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Bu kino uchun videolar mavjud emas",
                    show_alert: true
                );
                return;
            }

            VideoService::showVideos($bot, $db, (int)$movieId, Validator::isAdmin($bot));
            $bot->answerCallbackQuery();
        });

        // Like/unlike movie
        $bot->onCallbackQueryData('like_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            try {
                $isLiked = Movie::toggleLike($db, $bot->userId(), (int)$movieId);

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
            } catch (\Exception $e) {
                $bot->answerCallbackQuery(
                    text: "⚠️ " . $e->getMessage(),
                    show_alert: true
                );
            }
        });
    }

    /**
     * Register video-related callbacks
     */
    private static function registerVideoCallbacks(Nutgram $bot, PDO $db): void
    {
        // Play video
        $bot->onCallbackQueryData('play_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            VideoService::playVideo($bot, $db, (int)$videoId);
        });

        // Add video to movie
        $bot->onCallbackQueryData('add_video_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            VideoService::startAddVideo($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        // Edit video
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

            State::set($bot, 'state', "edit_video_{$videoId}");

            $message = "✏️ <b>Videoni tahrirlash</b>\n\n" .
                "🎬 <b>Kino:</b> {$video['movie_title']}\n" .
                "📹 <b>Video:</b> {$video['title']}\n" .
                "🔢 <b>Qism:</b> {$video['part_number']}\n\n" .
                "Tahrirlash uchun yangi video yuboring yoki quyidagi amallardan birini tanlang:";

            $keyboard = Keyboard::getInlineKeyboard([
                [
                    Keyboard::getCallbackButton("✏️ Sarlavhani tahrirlash", "edit_video_title_{$videoId}"),
                    Keyboard::getCallbackButton("✏️ Qism raqamini tahrirlash", "edit_video_part_{$videoId}")
                ],
                [
                    Keyboard::getCallbackButton("🔙 Orqaga", "manage_videos_{$video['movie_id']}")
                ]
            ]);

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );
            
            $bot->answerCallbackQuery();
        });

        // Edit video title
        $bot->onCallbackQueryData('edit_video_title_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
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

            State::set($bot, 'state', "edit_video_title_{$videoId}");

            $bot->sendMessage(
                text: "✏️ <b>Video sarlavhasini tahrirlash</b>\n\n" .
                    "Joriy sarlavha: <b>{$video['title']}</b>\n\n" .
                    "Yangi sarlavhani kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );
            
            $bot->answerCallbackQuery();
        });

        // Edit video part number
        $bot->onCallbackQueryData('edit_video_part_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
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

            State::set($bot, 'state', "edit_video_part_{$videoId}");

            $bot->sendMessage(
                text: "✏️ <b>Video qism raqamini tahrirlash</b>\n\n" .
                    "Joriy qism raqami: <b>{$video['part_number']}</b>\n\n" .
                    "Yangi qism raqamini kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );
            
            $bot->answerCallbackQuery();
        });

        // Delete video
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

            $message = "🗑 <b>Videoni o'chirish</b>\n\n" .
                "🎬 <b>Kino:</b> {$video['movie_title']}\n" .
                "📹 <b>Video:</b> {$video['title']}\n" .
                "🔢 <b>Qism:</b> {$video['part_number']}\n\n" .
                "Videoni o'chirishni tasdiqlaysizmi?";

            $bot->editMessageText(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::confirmDelete('video', $videoId)
            );
            
            $bot->answerCallbackQuery();
        });

        // Confirm delete video
        $bot->onCallbackQueryData('confirm_delete_video_([0-9]+)', function (Nutgram $bot, $videoId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            VideoService::deleteVideo($bot, $db, (int)$videoId);
            $bot->answerCallbackQuery(text: "✅ Video o'chirildi!");
        });

        // Manage videos
        $bot->onCallbackQueryData('manage_videos_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            VideoService::showVideos($bot, $db, (int)$movieId, true);
            $bot->answerCallbackQuery();
        });
    }

    /**
     * Register category-related callbacks
     */
    private static function registerCategoryCallbacks(Nutgram $bot, PDO $db): void
    {
        // Show categories
        $bot->onCallbackQueryData('categories', function (Nutgram $bot) use ($db) {
            CategoryService::showCategoryList($bot, $db);
            $bot->answerCallbackQuery();
        });

        // Show category movies
        $bot->onCallbackQueryData('category_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
            CategoryService::showCategoryMovies($bot, $db, (int)$categoryId);
            $bot->answerCallbackQuery();
        });

        // Edit category
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

            $message = "✏️ <b>Kategoriyani tahrirlash</b>\n\n" .
                "Kategoriya: <b>{$category['name']}</b>\n\n" .
                "Nimani tahrirlash kerak?";

            $keyboard = Keyboard::getInlineKeyboard([
                [
                    Keyboard::getCallbackButton("✏️ Nom", "edit_category_name_{$categoryId}"),
                    Keyboard::getCallbackButton("✏️ Tavsif", "edit_category_description_{$categoryId}")
                ],
                [
                    Keyboard::getCallbackButton("🔙 Orqaga", "categories")
                ]
            ]);

            $bot->sendMessage(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );
            
            $bot->answerCallbackQuery();
        });

        // Edit category name
        $bot->onCallbackQueryData('edit_category_name_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
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

            State::set($bot, 'state', "edit_category_name_{$categoryId}");

            $bot->sendMessage(
                text: "✏️ <b>Kategoriya nomini tahrirlash</b>\n\n" .
                    "Joriy nom: <b>{$category['name']}</b>\n\n" .
                    "Yangi nomni kiriting:",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );
            
            $bot->answerCallbackQuery();
        });

        // Edit category description
        $bot->onCallbackQueryData('edit_category_description_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
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

            State::set($bot, 'state', "edit_category_description_{$categoryId}");

            $description = $category['description'] ?? "Tavsif yo'q";
            
            $bot->sendMessage(
                text: "✏️ <b>Kategoriya tavsifini tahrirlash</b>\n\n" .
                    "Joriy tavsif: <b>{$description}</b>\n\n" .
                    "Yangi tavsifni kiriting (o'chirish uchun '-' kiriting):",
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );
            
            $bot->answerCallbackQuery();
        });

        // Delete category
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

            $message = "🗑 <b>Kategoriyani o'chirish</b>\n\n" .
                "Kategoriya: <b>{$category['name']}</b>\n" .
                "Tavsif: <b>" . ($category['description'] ?? "-") . "</b>\n" .
                "Kinolar soni: <b>{$category['movies_count']}</b>\n\n" .
                "Kategoriyani o'chirishni tasdiqlaysizmi?";

            $bot->editMessageText(
                text: $message,
                parse_mode: 'HTML',
                reply_markup: Keyboard::confirmDelete('category', $categoryId)
            );
            
            $bot->answerCallbackQuery();
        });

        // Confirm delete category
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

        // Manage movie categories
        $bot->onCallbackQueryData('manage_movie_categories_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            CategoryService::showCategorySelector($bot, $db, (int)$movieId);
            $bot->answerCallbackQuery();
        });

        // Select category for movie
        $bot->onCallbackQueryData('select_category_([0-9]+)', function (Nutgram $bot, $categoryId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
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
            
            if (State::isInState($bot, 'add_movie_confirm')) {
                CategoryService::updateCategorySelectorForCreation($bot, $db, $selectedCategories);
            } else if ($movieId) {
                CategoryService::updateCategorySelector($bot, $db, (int)$movieId, $selectedCategories);
            }

            $bot->answerCallbackQuery();
        });

        // Save selected categories
        $bot->onCallbackQueryData('save_categories', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            if (State::isInState($bot, 'add_movie_confirm')) {
                $bot->answerCallbackQuery(text: "✅ Saqlandi");
                $bot->sendMessage(
                    text: "✅ Kategoriyalar saqlandi! Davom etish uchun \"✅ Tasdiqlash\" tugmasini bosing.",
                    reply_markup: Keyboard::confirm()
                );
            } else {
                $movieId = State::get($bot, 'editing_movie_id');
                $selectedCategories = State::get($bot, 'selected_categories') ?? [];

                if (!$movieId) {
                    $bot->answerCallbackQuery(text: "⚠️ Kino topilmadi");
                    return;
                }

                try {
                    $categoryIds = array_map('intval', $selectedCategories);
                    Category::saveMovieCategories($db, (int)$movieId, $categoryIds);

                    $bot->answerCallbackQuery(text: "✅ Saqlandi");
                    MovieService::showMovie($bot, $db, (int)$movieId);
                    State::clear($bot, ['editing_movie_id', 'selected_categories']);
                } catch (\Exception $e) {
                    $bot->answerCallbackQuery(text: "⚠️ Xatolik");
                    $bot->sendMessage(text: "⚠️ Xatolik: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Register channel-related callbacks
     */
    private static function registerChannelCallbacks(Nutgram $bot, PDO $db): void
    {
        // Add channel
        $bot->onCallbackQueryData('add_channel', function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            State::set($bot, 'state', 'add_channel');
            $bot->sendMessage(
                text: Text::addChannel(),
                parse_mode: 'HTML',
                reply_markup: Keyboard::cancel()
            );
            
            $bot->answerCallbackQuery();
        });

        // Delete channel
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

        // Check subscription
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

    /**
     * Register admin-related callbacks
     */
    private static function registerAdminCallbacks(Nutgram $bot, PDO $db): void
    {
        // Edit movie
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

        // Edit movie title
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

        // Edit movie year
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

        // Edit movie description
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

        // Edit movie photo
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

        // Delete movie
        $bot->onCallbackQueryData('delete_movie_([0-9]+)', function (Nutgram $bot, $movieId) use ($db) {
            if (!Validator::isAdmin($bot)) {
                $bot->answerCallbackQuery(
                    text: "⚠️ Sizda yetarli huquq yo'q",
                    show_alert: true
                );
                return;
            }

            MovieService::confirmDeleteMovie($bot, $db, (int)$movieId);
        });

        // Confirm delete movie
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

        // Refresh statistics
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
    }
}