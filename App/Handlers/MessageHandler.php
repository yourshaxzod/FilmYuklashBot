<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, CategoryService, ChannelService, StatisticsService, VideoService};
use App\Models\{Movie, Video, Category, Channel, User};
use App\Helpers\{Button, Menu, State, Validator, Keyboard, Text};
use PDO;

class MessageHandler
{
    /**
     * Register message handlers
     */
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onMessage(function (Nutgram $bot) use ($db) {
            $text = $bot->message()->text;

            // Skip if not a text message
            if (!$text) {
                return;
            }

            $state = State::getState($bot);
            $screen = State::getScreen($bot);

            // Handle back button
            if ($text === Button::BACK) {
                self::handleBackButton($bot, $screen);
                return;
            }

            // Handle cancel button
            if ($text === Button::CANCEL) {
                Menu::showMainMenu($bot);
                return;
            }

            // Handle states
            if ($state) {
                if (self::handleMovieStates($bot, $db, $state, $text)) return;
                if (self::handleVideoStates($bot, $db, $state, $text)) return;
                if (self::handleCategoryStates($bot, $db, $state, $text)) return;
                if (self::handleChannelStates($bot, $db, $state, $text)) return;
                if (self::handleAdminStates($bot, $db, $state, $text)) return;
            }

            // Handle screens
            if ($screen) {
                switch ($screen) {
                    case State::MAIN:
                        self::handleMainScreenButtons($bot, $db, $text);
                        break;

                    case State::ADM_MAIN:
                        self::handleAdminScreenButtons($bot, $db, $text);
                        break;

                    case State::ADM_MOVIE:
                        self::handleAdminMovieButtons($bot, $db, $text);
                        break;

                    case State::ADM_CATEGORY:
                        self::handleAdminCategoryButtons($bot, $db, $text);
                        break;

                    default:
                        Menu::showMainMenu($bot);
                        break;
                }
                return;
            }

            // Default: show main menu
            Menu::showMainMenu($bot);
        });
    }

    /**
     * Handle back button for different screens
     */
    private static function handleBackButton(Nutgram $bot, ?string $screen): void
    {
        switch ($screen) {
            case State::ADM_MAIN:
                Menu::showMainMenu($bot);
                break;

            case State::ADM_MOVIE:
            case State::ADM_CATEGORY:
            case State::ADM_CHANNEL:
            case State::ADM_BROADCAST:
            case State::ADM_STATISTIC:
                Menu::showAdminMenu($bot);
                break;

            default:
                Menu::showMainMenu($bot);
                break;
        }
    }

    /**
     * Handle main screen buttons
     */
    private static function handleMainScreenButtons(Nutgram $bot, PDO $db, string $text): void
    {
        switch ($text) {
            case Button::SEARCH:
                Menu::showSearchMenu($bot);
                break;

            case Button::FAVORITE:
                Menu::showFavoriteMenu($bot, $db);
                break;

            case Button::TRENDING:
                Menu::showTrendingMenu($bot, $db);
                break;

            case Button::CATEGORY:
                Menu::showCategoriesMenu($bot, $db);
                break;

            case Button::RECOMMENDATION:
                Menu::showRecommendationsMenu($bot, $db);
                break;

            case Button::PANEL:
                if (Validator::isAdmin($bot)) {
                    Menu::showAdminMenu($bot);
                }
                break;

            default:
                Menu::showMainMenu($bot);
                break;
        }
    }

    /**
     * Handle admin screen buttons
     */
    private static function handleAdminScreenButtons(Nutgram $bot, PDO $db, string $text): void
    {
        if (!Validator::isAdmin($bot)) return;

        switch ($text) {
            case Button::MOVIE:
                Menu::showMovieManageMenu($bot);
                break;

            case Button::CATEGORY:
                Menu::showCategoryManageMenu($bot);
                break;

            case Button::CHANNEL:
                ChannelService::showChannels($bot, $db);
                break;

            case Button::STATISTIC:
                StatisticsService::showStats($bot, $db);
                break;

            case Button::MESSAGE:
                Menu::showBroadcastMenu($bot);
                break;

            default:
                Menu::showAdminMenu($bot);
                break;
        }
    }

    /**
     * Handle admin movie buttons
     */
    private static function handleAdminMovieButtons(Nutgram $bot, PDO $db, string $text): void
    {
        if (!Validator::isAdmin($bot)) return;

        switch ($text) {
            case Button::ADD:
                Menu::showAddMovieGuide($bot);
                break;

            case Button::DEL:
                State::setState($bot, 'delete_movie_id');
                $bot->sendMessage(
                    text: "🗑 <b>O'chirish uchun kino ID raqamini kiriting:</b>",
                    parse_mode: 'HTML',
                    reply_markup: Keyboard::cancel()
                );
                break;

            case Button::EDIT:
                State::setState($bot, 'edit_movie_id');
                $bot->sendMessage(
                    text: Text::editMovie(),
                    parse_mode: 'HTML',
                    reply_markup: Keyboard::cancel()
                );
                break;

            case Button::LIST:
                MovieService::showMoviesList($bot, $db);
                break;

            default:
                Menu::showMovieManageMenu($bot);
                break;
        }
    }

    /**
     * Handle admin category buttons
     */
    private static function handleAdminCategoryButtons(Nutgram $bot, PDO $db, string $text): void
    {
        if (!Validator::isAdmin($bot)) {
            Menu::showMainMenu($bot);
            return;
        }

        switch ($text) {
            case Button::ADD:
                State::setState($bot, 'add_category_name');
                Menu::showAddCategoryGuide($bot);
                break;

            case Button::LIST:
                CategoryService::showCategoryList($bot, $db, true);
                break;

            default:
                Menu::showCategoryManageMenu($bot);
                break;
        }
    }

    /**
     * Handle movie-related states
     */
    private static function handleMovieStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        switch ($state) {
            case State::SEARCH:
                MovieService::search($bot, $db, $text);
                return true;

            case "add_movie_title":
                if (!Validator::validateMovieTitle($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri kino nomi! Kamida 2 ta, ko'pi bilan 255 ta belgi bo'lishi kerak."
                    );
                    return true;
                }
                State::set($bot, "movie_title", $text);
                State::setState($bot, "add_movie_year");

                $bot->sendMessage("📅 Endi kino yilini kiriting (masalan: 2023):");
                return true;

            case "add_movie_year":
                $year = (int) $text;

                if (!Validator::validateMovieYear($year)) {
                    $bot->sendMessage("⚠️ Noto'g'ri yil! 1900 dan hozirgi yilgacha bo'lgan son kiriting:");
                    return true;
                }

                State::set($bot, "movie_year", $year);
                State::setState($bot, "add_movie_description");

                $bot->sendMessage("📝 Endi kino haqida tavsif kiriting:");
                return true;

            case "add_movie_description":
                if (!Validator::validateMovieDescription($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri tavsif! Kamida 10 ta, ko'pi bilan 4000 ta belgi bo'lishi kerak."
                    );
                    return true;
                }
                
                State::set($bot, "movie_description", $text);
                State::setState($bot, "add_movie_photo");

                $bot->sendMessage("🖼 Endi kino posterini (rasm) yuboring:");
                return true;

            case "add_movie_confirm":
                if ($text === Button::CONFIRM) {
                    try {
                        $movieData = [
                            "title" => State::get($bot, "movie_title"),
                            "description" => State::get($bot, "movie_description"),
                            "year" => State::get($bot, "movie_year"),
                            "file_id" => State::get($bot, "movie_photo"),
                        ];

                        $categoryIds = array_map('intval', State::get($bot, "selected_categories") ?? []);

                        $movieId = Movie::create($db, $movieData, $categoryIds);

                        $bot->sendMessage(
                            text: "✅ Kino muvaffaqiyatli qo'shildi! ID: {$movieId}\n\nEndi kinoga video qo'shish uchun video yuboring:",
                            reply_markup: Keyboard::cancel()
                        );

                        State::set($bot, "state", "add_video");
                        State::set($bot, "movie_id", (string) $movieId);

                        State::clear($bot, [
                            "movie_title",
                            "movie_description",
                            "movie_year",
                            "movie_photo",
                            "selected_categories",
                        ]);
                    } catch (\Exception $e) {
                        $bot->sendMessage(
                            text: "⚠️ Kino qo'shishda xatolik: " . $e->getMessage(),
                            reply_markup: Keyboard::mainMenu($bot)
                        );
                        State::clearAll($bot);
                    }
                } else {
                    $bot->sendMessage(
                        text: "❌ Kino qo'shish bekor qilindi.",
                        reply_markup: Keyboard::mainMenu($bot)
                    );
                    State::clearAll($bot);
                }
                return true;

            case "edit_movie_id":
                if (!is_numeric($text)) {
                    $bot->sendMessage("⚠️ Kino ID raqam bo'lishi kerak!");
                    return true;
                }

                $movieId = (int) $text;
                $movie = Movie::findById($db, $movieId);

                if (!$movie) {
                    $bot->sendMessage("⚠️ Bu ID bilan kino topilmadi!");
                    return true;
                }

                $categories = Category::getByMovieId($db, $movieId);

                $bot->sendPhoto(
                    photo: $movie["file_id"] ?? "https://via.placeholder.com/400x600?text=No+Image",
                    caption: Text::movieInfo($movie, $movie["video_count"], $categories, true),
                    parse_mode: "HTML",
                    reply_markup: Keyboard::movieEditActions($movieId)
                );

                State::clearAll($bot);
                return true;

            case "delete_movie_id":
                if (!is_numeric($text)) {
                    $bot->sendMessage("⚠️ Kino ID raqam bo'lishi kerak!");
                    return true;
                }

                $movieId = (int) $text;
                $movie = Movie::findById($db, $movieId);

                if (!$movie) {
                    $bot->sendMessage("⚠️ Bu ID bilan kino topilmadi!");
                    return true;
                }

                $categories = Category::getByMovieId($db, $movieId);

                $message = "🗑 <b>Kinoni o'chirish</b>\n\n";
                $message .= Text::movieInfo($movie, Video::getCountByMovieId($db, $movieId), $categories);
                $message .= "\n\nKinoni o'chirishni tasdiqlaysizmi?";

                $bot->sendMessage(
                    text: $message,
                    parse_mode: "HTML",
                    reply_markup: Keyboard::confirmDelete("movie", $movieId)
                );

                State::clearAll($bot);
                return true;
                
            // Handle movie title edit
            case (preg_match('/^edit_movie_title_(\d+)$/', $state, $matches) ? true : false):
                $movieId = (int) $matches[1];

                if (!Validator::validateMovieTitle($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri kino nomi! Kamida 2 ta, ko'pi bilan 255 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                try {
                    Movie::update($db, $movieId, ["title" => $text]);
                    $bot->sendMessage("✅ Kino nomi muvaffaqiyatli yangilandi!");
                    MovieService::showMovie($bot, $db, $movieId);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        "⚠️ Kino nomini yangilashda xatolik: " . $e->getMessage()
                    );
                }
                return true;

            // Handle movie year edit
            case (preg_match('/^edit_movie_year_(\d+)$/', $state, $matches) ? true : false):
                $movieId = (int) $matches[1];
                $year = (int) $text;

                if (!Validator::validateMovieYear($year)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri yil! 1900 dan hozirgi yilgacha bo'lgan son kiriting:"
                    );
                    return true;
                }

                try {
                    Movie::update($db, $movieId, ["year" => $year]);
                    $bot->sendMessage("✅ Kino yili muvaffaqiyatli yangilandi!");
                    MovieService::showMovie($bot, $db, $movieId);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        "⚠️ Kino yilini yangilashda xatolik: " . $e->getMessage()
                    );
                }
                return true;

            // Handle movie description edit
            case (preg_match('/^edit_movie_description_(\d+)$/', $state, $matches) ? true : false):
                $movieId = (int) $matches[1];

                if (!Validator::validateMovieDescription($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri tavsif! Kamida 10 ta, ko'pi bilan 4000 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                try {
                    Movie::update($db, $movieId, ["description" => $text]);
                    $bot->sendMessage("✅ Kino tavsifi muvaffaqiyatli yangilandi!");
                    MovieService::showMovie($bot, $db, $movieId);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        "⚠️ Kino tavsifini yangilashda xatolik: " . $e->getMessage()
                    );
                }
                return true;
        }

        return false;
    }

    /**
     * Handle video-related states
     */
    private static function handleVideoStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        switch ($state) {
            case "add_video_title":
                if (!Validator::validateVideoTitle($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri video sarlavhasi! Kamida 2 ta, ko'pi bilan 255 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                State::set($bot, "video_title", $text);
                State::set($bot, "state", "add_video_part");

                $movieId = State::get($bot, "movie_id");
                $nextPart = Video::getNextPartNumber($db, (int) $movieId);

                $bot->sendMessage("🔢 Endi video qism raqamini kiriting (masalan: {$nextPart}):");
                return true;

            case "add_video_part":
                if (!Validator::validateVideoPartNumber($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri qism raqami! 1 dan 1000 gacha son bo'lishi kerak."
                    );
                    return true;
                }

                $partNumber = (int) $text;
                $movieId = State::get($bot, "movie_id");

                $existing = Video::findByPart($db, (int) $movieId, $partNumber);
                if ($existing) {
                    $bot->sendMessage(
                        "⚠️ Bu qism raqami allaqachon mavjud. Boshqa raqam kiriting:"
                    );
                    return true;
                }

                State::set($bot, "video_part", (string) $partNumber);

                $fileId = State::get($bot, "file_id");
                if ($fileId) {
                    // Process video that was stored earlier
                    $movieId = (int)State::get($bot, "movie_id");
                    $movie = Movie::findById($db, $movieId);
                    
                    $videoData = [
                        "movie_id" => $movieId,
                        "title" => State::get($bot, "video_title"),
                        "part_number" => (int)State::get($bot, "video_part"),
                        "file_id" => $fileId
                    ];

                    try {
                        $videoId = Video::create($db, $videoData);
                        
                        $message = "✅ Video muvaffaqiyatli qo'shildi!\n\n" .
                            "🎬 <b>Kino:</b> {$movie['title']}\n" .
                            "📹 <b>Video:</b> {$videoData['title']}\n" .
                            "🔢 <b>Qism:</b> {$videoData['part_number']}\n\n" .
                            "Yana video qo'shish uchun video yuboring yoki bosh menyuga qaytish uchun /start buyrug'ini bosing.";

                        $bot->sendMessage(
                            text: $message,
                            parse_mode: "HTML",
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
                } else {
                    $bot->sendMessage("📹 Endi videoni yuboring:");
                    State::set($bot, "state", "add_video");
                }
                
                return true;

            // Handle video title edit
            case (preg_match('/^edit_video_title_(\d+)$/', $state, $matches) ? true : false):
                $videoId = (int) $matches[1];

                if (!Validator::validateVideoTitle($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri video sarlavhasi! Kamida 2 ta, ko'pi bilan 255 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                try {
                    Video::update($db, $videoId, ["title" => $text]);
                    $video = Video::findById($db, $videoId);
                    $bot->sendMessage("✅ Video sarlavhasi muvaffaqiyatli yangilandi!");
                    VideoService::showVideos($bot, $db, $video["movie_id"], true);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        "⚠️ Video sarlavhasini yangilashda xatolik: " . $e->getMessage()
                    );
                }
                return true;

            // Handle video part edit
            case (preg_match('/^edit_video_part_(\d+)$/', $state, $matches) ? true : false):
                $videoId = (int) $matches[1];

                if (!Validator::validateVideoPartNumber($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri qism raqami! 1 dan 1000 gacha son bo'lishi kerak."
                    );
                    return true;
                }

                $partNumber = (int) $text;

                try {
                    $video = Video::findById($db, $videoId);
                    $existing = Video::findByPart($db, $video["movie_id"], $partNumber);
                    
                    if ($existing && $existing["id"] != $videoId) {
                        $bot->sendMessage(
                            "⚠️ Bu qism raqami allaqachon mavjud. Boshqa raqam kiriting:"
                        );
                        return true;
                    }

                    Video::update($db, $videoId, ["part_number" => $partNumber]);
                    $bot->sendMessage("✅ Video qism raqami muvaffaqiyatli yangilandi!");
                    VideoService::showVideos($bot, $db, $video["movie_id"], true);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        "⚠️ Video qism raqamini yangilashda xatolik: " . $e->getMessage()
                    );
                }
                return true;
        }

        return false;
    }

    /**
     * Handle category-related states
     */
    private static function handleCategoryStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        switch ($state) {
            case "add_category_name":
                if (!Validator::validateCategoryName($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri kategoriya nomi! Kamida 2 ta, ko'pi bilan 100 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                $existing = Category::findByName($db, $text);
                if ($existing) {
                    $bot->sendMessage(
                        "⚠️ Bu nomdagi kategoriya allaqachon mavjud. Boshqa nom kiriting:"
                    );
                    return true;
                }

                State::set($bot, "category_name", $text);
                State::set($bot, "state", "add_category_description");

                $bot->sendMessage(
                    "📝 Endi kategoriya haqida tavsif kiriting (ixtiyoriy, o'tkazib yuborish uchun '-' kiriting):"
                );
                return true;

            case "add_category_description":
                $description = $text === "-" ? null : $text;

                if ($description !== null && mb_strlen($description) > 1000) {
                    $bot->sendMessage(
                        "⚠️ Tavsif juda uzun! Ko'pi bilan 1000 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                try {
                    $categoryData = [
                        "name" => State::get($bot, "category_name"),
                        "description" => $description,
                    ];

                    $categoryId = Category::create($db, $categoryData);

                    $bot->sendMessage(
                        text: "✅ Kategoriya muvaffaqiyatli qo'shildi! ID: {$categoryId}",
                        reply_markup: Keyboard::mainMenu($bot)
                    );

                    CategoryService::showCategoryList($bot, $db, true);

                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        text: "⚠️ Kategoriya qo'shishda xatolik: " . $e->getMessage(),
                        reply_markup: Keyboard::mainMenu($bot)
                    );
                    State::clearAll($bot);
                }
                return true;

            // Handle category name edit
            case (preg_match('/^edit_category_name_(\d+)$/', $state, $matches) ? true : false):
                $categoryId = (int) $matches[1];

                if (!Validator::validateCategoryName($text)) {
                    $bot->sendMessage(
                        "⚠️ Noto'g'ri kategoriya nomi! Kamida 2 ta, ko'pi bilan 100 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                $existing = Category::findByName($db, $text);
                if ($existing && $existing["id"] != $categoryId) {
                    $bot->sendMessage(
                        "⚠️ Bu nomdagi kategoriya allaqachon mavjud. Boshqa nom kiriting:"
                    );
                    return true;
                }

                try {
                    Category::update($db, $categoryId, ["name" => $text]);
                    $bot->sendMessage("✅ Kategoriya nomi muvaffaqiyatli yangilandi!");
                    CategoryService::showCategoryList($bot, $db, true);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        "⚠️ Kategoriya nomini yangilashda xatolik: " . $e->getMessage()
                    );
                }
                return true;

            // Handle category description edit
            case (preg_match('/^edit_category_description_(\d+)$/', $state, $matches) ? true : false):
                $categoryId = (int) $matches[1];

                $description = $text === "-" ? null : $text;

                if ($description !== null && mb_strlen($description) > 1000) {
                    $bot->sendMessage(
                        "⚠️ Tavsif juda uzun! Ko'pi bilan 1000 ta belgi bo'lishi kerak."
                    );
                    return true;
                }

                try {
                    Category::update($db, $categoryId, ["description" => $description]);
                    $bot->sendMessage("✅ Kategoriya tavsifi muvaffaqiyatli yangilandi!");
                    CategoryService::showCategoryList($bot, $db, true);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        "⚠️ Kategoriya tavsifini yangilashda xatolik: " . $e->getMessage()
                    );
                }
                return true;
        }

        return false;
    }

    /**
     * Handle channel-related states
     */
    private static function handleChannelStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        if (!Validator::isAdmin($bot)) {
            return false;
        }

        switch ($state) {
            case "add_channel":
                try {
                    $channelInfo = Channel::checkBotIsAdmin($bot, $text);

                    if (!$channelInfo) {
                        $bot->sendMessage(
                            text: "⚠️ <b>Xatolik:</b> Kanal topilmadi yoki bot admin emas. Iltimos tekshiring va qayta urinib ko'ring.",
                            parse_mode: "HTML"
                        );
                        return true;
                    }

                    $existing = Channel::findByUsername($db, $channelInfo["username"]);
                    if ($existing) {
                        $bot->sendMessage(
                            text: "⚠️ <b>Xatolik:</b> Bu kanal allaqachon qo'shilgan.",
                            parse_mode: "HTML"
                        );
                        return true;
                    }

                    $channelId = Channel::create($db, $channelInfo);

                    $bot->sendMessage(
                        text: "✅ <b>Kanal muvaffaqiyatli qo'shildi!</b>\n\n" .
                            "📢 <b>Kanal:</b> @{$channelInfo["username"]}\n" .
                            "💬 <b>Nomi:</b> {$channelInfo["title"]}",
                        parse_mode: "HTML",
                        reply_markup: Keyboard::adminMenu()
                    );

                    ChannelService::showChannels($bot, $db);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        text: "⚠️ <b>Xatolik:</b> " . $e->getMessage(),
                        parse_mode: "HTML"
                    );
                }
                return true;
                
            case "delete_channel":
                if (!is_numeric($text)) {
                    $bot->sendMessage("⚠️ Kanal ID raqam bo'lishi kerak!");
                    return true;
                }

                $channelId = (int) $text;

                try {
                    $channel = Channel::find($db, $channelId);
                    if (!$channel) {
                        $bot->sendMessage("⚠️ Bu ID bilan kanal topilmadi!");
                        return true;
                    }

                    Channel::delete($db, $channelId);

                    $bot->sendMessage(
                        text: "✅ <b>Kanal muvaffaqiyatli o'chirildi!</b>",
                        parse_mode: "HTML",
                        reply_markup: Keyboard::adminMenu()
                    );

                    ChannelService::showChannels($bot, $db);
                    State::clearAll($bot);
                } catch (\Exception $e) {
                    $bot->sendMessage(
                        text: "⚠️ <b>Xatolik:</b> " . $e->getMessage(),
                        parse_mode: "HTML"
                    );
                }
                return true;
        }

        return false;
    }

    /**
     * Handle admin-related states
     */
    private static function handleAdminStates(Nutgram $bot, PDO $db, string $state, string $text): bool
    {
        if (!Validator::isAdmin($bot)) {
            return false;
        }

        switch ($state) {
            case "broadcast_message":
                State::set($bot, "broadcast_text", $text);
                State::set($bot, "state", "broadcast_confirm");

                $bot->sendMessage(
                    text: "📬 <b>Quyidagi xabarni yuborishni tasdiqlaysizmi?</b>\n\n" . $text,
                    parse_mode: "HTML",
                    reply_markup: Keyboard::confirm()
                );
                return true;

            case "broadcast_confirm":
                if ($text === Button::CONFIRM) {
                    $broadcastText = State::get($bot, "broadcast_text");

                    if (empty($broadcastText)) {
                        $bot->sendMessage("⚠️ Xabar topilmadi!");
                        Menu::showAdminMenu($bot);
                        return true;
                    }

                    try {
                        $users = User::getAll($db, "active", 1000);
                        $userIds = array_column($users, "user_id");

                        $bot->sendMessage(
                            text: "📬 <b>Xabar yuborilmoqda...</b>\n\n" .
                                "Jami foydalanuvchilar: " . count($userIds),
                            parse_mode: "HTML"
                        );

                        $options = [
                            "parse_mode" => "HTML",
                            "disable_web_page_preview" => true,
                        ];

                        $results = User::broadcast($bot, $db, $broadcastText, $options);

                        $bot->sendMessage(
                            text: "✅ <b>Xabar yuborildi!</b>\n\n" .
                                "✅ Yuborildi: {$results["sent"]}\n" .
                                "❌ Yuborilmadi: {$results["failed"]}\n" .
                                "⚠️ O'tkazib yuborildi: {$results["skipped"]}",
                            parse_mode: "HTML",
                            reply_markup: Keyboard::adminMenu()
                        );
                        
                        State::clearAll($bot);
                    } catch (\Exception $e) {
                        $bot->sendMessage(
                            text: "⚠️ Xabar yuborishda xatolik: " . $e->getMessage(),
                            reply_markup: Keyboard::adminMenu()
                        );
                        State::clearAll($bot);
                    }
                } else {
                    $bot->sendMessage(
                        text: "❌ Xabar yuborish bekor qilindi.",
                        reply_markup: Keyboard::adminMenu()
                    );
                    State::clearAll($bot);
                }
                return true;
        }

        return false;
    }
}