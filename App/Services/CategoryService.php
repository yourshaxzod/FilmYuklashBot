<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Category};
use App\Helpers\{Formatter, Keyboard, Validator, Menu, State, Text, Config};
use PDO;

class CategoryService
{
    public static function showCategoryList(Nutgram $bot, PDO $db, bool $isAdmin = false): void
    {
        try {
            $categories = Category::getAll($db);

            if (empty($categories)) {
                $message = "🏷 <b>Kategoriyalar</b>\n\n";
                $message .= "Hozircha kategoriyalar yo'q.";

                if ($isAdmin) {
                    $message .= "\n\nAdmin sifatida kategoriya qo'shishingiz mumkin!";
                }

                $bot->sendMessage(
                    text: $message,
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::categoryManageMenu()
                );
                return;
            }

            $message = "🏷 <b>Kategoriyalar</b>\n\n";
            $message .= "Kategoriyalar soni: " . count($categories) . "\n\n";
            $message .= "Kerakli kategoriyani tanlang:";

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

    public static function showCategoryMovies(Nutgram $bot, PDO $db, int $categoryId, int $page = 1): void
    {
        try {
            $category = Category::findById($db, $categoryId);
            if (!$category) {
                $bot->sendMessage(
                    text: Text::categoryNotFound(),
                    reply_markup: Keyboard::mainMenu($bot)
                );
                return;
            }

            $perPage = Config::getItemsPerPage();
            $offset = ($page - 1) * $perPage;

            $movies = Movie::getByCategoryId($db, $categoryId, $bot->userId(), $perPage, $offset);
            $totalMovies = Movie::getCountByCategoryId($db, $categoryId);

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

            $totalPages = ceil($totalMovies / $perPage);

            $message = "🏷 <b>Kategoriya:</b> {$category['name']} (sahifa {$page}/{$totalPages})\n\n";

            if (!empty($category['description'])) {
                $message .= "{$category['description']}\n\n";
            }

            $message .= "Bu kategoriyadagi kinolar soni: {$totalMovies}\n\n";
            $message .= "Quyidagi kinolardan birini tanlang:";

            $movieButtons = [];
            foreach ($movies as $movie) {
                $movieButtons[] = [Keyboard::getCallbackButton("🎬 {$movie['title']}", "movie_{$movie['id']}")];
            }

            $movieButtons[] = [Keyboard::getCallbackButton("🔙 Kategoriyalarga qaytish", "categories")];

            $keyboard = Keyboard::pagination("category_{$categoryId}", $page, $totalPages, $movieButtons);

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

    public static function startEditCategory(Nutgram $bot, PDO $db, int $categoryId): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

            $category = Category::findById($db, $categoryId);
            if (!$category) {
                $bot->sendMessage(text: Text::categoryNotFound());
                return;
            }

            $message = "✏️ <b>Kategoriyani tahrirlash</b>\n\n";
            $message .= "Kategoriya: <b>{$category['name']}</b>\n";

            if (!empty($category['description'])) {
                $message .= "Tavsif: <b>{$category['description']}</b>\n";
            }

            $message .= "\nNimani tahrirlash kerak?";

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

    public static function showCategorySelector(Nutgram $bot, PDO $db, int $movieId): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                $bot->sendMessage(text: Text::noPermission());
                return;
            }

            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->sendMessage(text: Text::movieNotFound());
                return;
            }

            $categories = Category::getAll($db);

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

            $currentCategories = Category::getByMovieId($db, $movieId);
            $selectedIds = array_column($currentCategories, 'id');

            State::set($bot, 'editing_movie_id', $movieId);
            State::set($bot, 'selected_categories', $selectedIds);

            $message = "🏷 <b>Kino kategoriyalarini tanlash</b>\n\n";
            $message .= "Kino: <b>{$movie['title']}</b>\n\n";
            $message .= "Bir yoki bir nechta kategoriyalarni tanlang:";

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

    public static function updateCategorySelector(Nutgram $bot, PDO $db, int $movieId, array $selectedIds): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            $movie = Movie::findById($db, $movieId);
            if (!$movie) {
                $bot->answerCallbackQuery(text: "⚠️ Kino topilmadi", show_alert: true);
                return;
            }

            $categories = Category::getAll($db);

            $message = "🏷 <b>Kino kategoriyalarini tanlash</b>\n\n";
            $message .= "Kino: <b>{$movie['title']}</b>\n\n";

            if (!empty($selectedIds)) {
                $selectedCategories = array_filter($categories, function ($cat) use ($selectedIds) {
                    return in_array($cat['id'], $selectedIds);
                });

                $selectedNames = array_column($selectedCategories, 'name');
                $message .= "Tanlangan: <b>" . implode(', ', $selectedNames) . "</b>\n\n";
            }

            $message .= "Bir yoki bir nechta kategoriyalarni tanlang:";

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

    public static function updateCategorySelectorForCreation(Nutgram $bot, PDO $db, array $selectedIds): void
    {
        try {
            if (!Validator::isAdmin($bot)) {
                return;
            }

            $categories = Category::getAll($db);

            $title = State::get($bot, 'movie_title');

            $message = "🏷 <b>Kino kategoriyalarini tanlash</b>\n\n";

            if ($title) {
                $message .= "Kino: <b>{$title}</b>\n\n";
            }

            if (!empty($selectedIds)) {
                $selectedCategories = array_filter($categories, function ($cat) use ($selectedIds) {
                    return in_array($cat['id'], $selectedIds);
                });

                $selectedNames = array_column($selectedCategories, 'name');
                $message .= "Tanlangan: <b>" . implode(', ', $selectedNames) . "</b>\n\n";
            }

            $message .= "Bir yoki bir nechta kategoriyalarni tanlang:";

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
