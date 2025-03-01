<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Category};
use App\Helpers\{Formatter, Keyboard, Validator, Menu, State, Text, Config};
use PDO;

/**
 * Service for handling category-related operations
 */
class CategoryService
{
    /**
     * Show all categories
     * 
     * @param Nutgram $bot Bot instance
     * @param PDO $db Database connection
     * @param bool $isAdmin Whether this is for admin mode
     * @return void
     */
    public static function showCategoryList(Nutgram $bot, PDO $db, bool $isAdmin = false): void
    {
        try {
            // Get all categories
            $categories = Category::getAll($db);

            // If no categories found
            if (empty($categories)) {
                $message = "🏷 <b>Kategoriyalar</b>\n\n";
                $message .= "Hozircha kategoriyalar yo'q.";

                if ($isAdmin) {
                    $message .= "\n\nAdmin sifatida kategoriya qo'shishingiz mumkin!";
                    $keyboard = Keyboard::getInlineKeyboard([
                        [Keyboard::getCallbackButton("➕ Kategoriya qo'shish", "add_category")]
                    ]);
                } else {
                    $keyboard = Keyboard::getInlineKeyboard([
                        [Keyboard::getCallbackButton("🔙 Orqaga", "back_to_main")]
                    ]);
                }

                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
                return;
            }

            // Create message with categories
            $message = "🏷 <b>Kategoriyalar</b>\n\n";
            $message .= "Kategoriyalar soni: " . count($categories) . "\n\n";
            $message .= "Kerakli kategoriyani tanlang:";

            // Create category buttons
            $keyboard = Keyboard::categoryList($categories, $isAdmin);

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Kategoriyalarni olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Show movies in a category
     * 
     * @param Nutgram $bot Bot instance
     * @param PDO $db Database connection
     * @param int $categoryId Category ID
     * @param int $page Page number
     * @return void
     */
    public static function showCategoryMovies(Nutgram $bot, PDO $db, int $categoryId, int $page = 1): void
    {
        try {
            // Get category info
            $category = Category::findById($db, $categoryId);
            if (!$category) {
                $bot->sendMessage(
                    text: Text::categoryNotFound(),
                    reply_markup: Keyboard::mainMenu($bot)
                );
                return;
            }

            // Get pagination parameters
            $perPage = Config::getItemsPerPage();
            $offset = ($page - 1) * $perPage;

            // Get movies in this category
            $movies = Movie::getByCategoryId($db, $categoryId, $bot->userId(), $perPage, $offset);
            $totalMovies = Movie::getCountByCategoryId($db, $categoryId);

            // If no movies found
            if (empty($movies)) {
                $message = "🏷 <b>Kategoriya:</b> {$category['name']}\n\n";

                if (!empty($category['description'])) {
                    $message .= "{$category['description']}\n\n";
                }

                $message .= "Bu kategoriyada hozircha kinolar yo'q.";

                $keyboard = Keyboard::getInlineKeyboard([
                    [Keyboard::getCallbackButton("🔙 Kategoriyalarga qaytish", "categories")]
                ]);

                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
                return;
            }

            // Calculate total pages
            $totalPages = ceil($totalMovies / $perPage);

            // Create message
            $message = "🏷 <b>Kategoriya:</b> {$category['name']} (sahifa {$page}/{$totalPages})\n\n";

            if (!empty($category['description'])) {
                $message .= "{$category['description']}\n\n";
            }

            $message .= "Bu kategoriyadagi kinolar soni: {$totalMovies}\n\n";
            $message .= "Quyidagi kinolardan birini tanlang:";

            // Create buttons for movies
            $movieButtons = [];
            foreach ($movies as $movie) {
                $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")];
            }

            // Add back button
            $movieButtons[] = [Keyboard::getCallbackButton("🔙 Kategoriyalarga qaytish", "categories")];

            // Add pagination
            $keyboard = Keyboard::pagination("category_{$categoryId}", $page, $totalPages, $movieButtons);

            // Send or update message
            if ($page === 1) {
                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            } else {
                $bot->editMessageText(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Kategoriya kinolarini olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Start category editing process
     * 
     * @param Nutgram $bot Bot instance
     * @param PDO $db Database connection
     * @param int $categoryId Category ID
     * @return void
     */
    public static function startEditCategory(Nutgram $bot, PDO $db, int $categoryId): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

            // Get category info
            $category = Category::findById($db, $categoryId);
            if (!$category) {
                $bot->sendMessage(text: Text::categoryNotFound());
                return;
            }

            // Create edit message
            $message = "✏️ <b>Kategoriyani tahrirlash</b>\n\n";
            $message .= "Kategoriya: <b>{$category['name']}</b>\n";

            if (!empty($category['description'])) {
                $message .= "Tavsif: <b>{$category['description']}</b>\n";
            }

            $message .= "\nNimani tahrirlash kerak?";

            // Create edit buttons
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
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Kategoriyani tahrirlashda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Show category selector for movies
     * 
     * @param Nutgram $bot Bot instance
     * @param PDO $db Database connection
     * @param int $movieId Movie ID
     * @return void
     */
    public static function showCategorySelector(Nutgram $bot, PDO $db, int $movieId): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

            // Get movie info
            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            // Get all categories
            $categories = Category::getAll($db);

            // If no categories found
            if (empty($categories)) {
                $message = "🏷 <b>Kategoriyalar</b>\n\n";
                $message .= "Hozircha kategoriyalar yo'q. Avval kategoriyalar qo'shing.";

                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::getInlineKeyboard([
                        [Keyboard::getCallbackButton("🔙 Orqaga", "movie_{$movieId}")]
                    ])
                );
                return;
            }

            // Get current movie categories
            $currentCategories = Category::getByMovieId($db, $movieId);
            $selectedIds = array_column($currentCategories, 'id');

            // Save editing state
            State::set($bot, 'editing_movie_id', $movieId);
            State::set($bot, 'selected_categories', $selectedIds);

            // Create message
            $message = "🏷 <b>Kino kategoriyalarini tanlash</b>\n\n";
            $message .= "Kino: <b>{$movie['title']}</b>\n\n";
            $message .= "Bir yoki bir nechta kategoriyalarni tanlang:";

            // Create selection keyboard
            $keyboard = Keyboard::categorySelector($categories, $selectedIds);

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Kategoriyalarni olishda xatolik: " . $e->getMessage(),
                reply_markup: Keyboard::mainMenu($bot)
            );
        }
    }

    /**
     * Update category selector with selected categories
     * 
     * @param Nutgram $bot Bot instance
     * @param PDO $db Database connection
     * @param int $movieId Movie ID
     * @param array $selectedIds Selected category IDs
     * @return void
     */
    public static function updateCategorySelector(Nutgram $bot, PDO $db, int $movieId, array $selectedIds): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

            // Get movie info
            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            // Get all categories
            $categories = Category::getAll($db);

            // Create message
            $message = "🏷 <b>Kino kategoriyalarini tanlash</b>\n\n";
            $message .= "Kino: <b>{$movie['title']}</b>\n\n";

            // Show selected categories
            if (!empty($selectedIds)) {
                $selectedCategories = array_filter($categories, function ($cat) use ($selectedIds) {
                    return in_array($cat['id'], $selectedIds);
                });

                $selectedNames = array_column($selectedCategories, 'name');
                $message .= "Tanlangan: <b>" . implode(', ', $selectedNames) . "</b>\n\n";
            }

            $message .= "Bir yoki bir nechta kategoriyalarni tanlang:";

            // Create updated selection keyboard
            $keyboard = Keyboard::categorySelector($categories, $selectedIds);

            $bot->editMessageText(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard
            );
        } catch (\Exception $e) {
            $bot->answerCallbackQuery(
                text: "⚠️ Xatolik: " . $e->getMessage(),
                show_alert: true
            );
        }
    }

    /**
     * Get all categories for cache
     * 
     * @param PDO $db Database connection
     * @return array Categories data
     */
    public static function getAllForCache(PDO $db): array
    {
        try {
            return Category::getAll($db);
        } catch (\Exception $e) {
            error_log("Kategoriyalarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }
}
