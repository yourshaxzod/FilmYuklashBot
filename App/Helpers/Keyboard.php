<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\{
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    KeyboardButton,
    ReplyKeyboardMarkup,
    ReplyKeyboardRemove
};

class Keyboard
{
    public static function mainMenu(Nutgram $bot): ReplyKeyboardMarkup
    {
        $keyboard = ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make(Button::SEARCH),
                KeyboardButton::make(Button::FAVORITE),
                KeyboardButton::make(Button::TRENDING),
            )
            ->addRow(
                KeyboardButton::make(Button::CATEGORY),
                KeyboardButton::make(Button::RECOMMENDATION)
            );

        if (Validator::isAdmin($bot)) {
            $keyboard->addRow(
                KeyboardButton::make(Button::PANEL)
            );
        }

        return $keyboard;
    }

    public static function adminMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make(Button::MOVIE),
                KeyboardButton::make(Button::CATEGORY)
            )
            ->addRow(
                KeyboardButton::make(Button::CHANNEL),
                KeyboardButton::make(Button::STATISTIC)
            )
            ->addRow(
                KeyboardButton::make(Button::MESSAGE)
            )
            ->addRow(
                KeyboardButton::make(Button::BACK)
            );
    }

    public static function movieManageMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make(Button::ADD),
                KeyboardButton::make(Button::DEL)
            )
            ->addRow(
                KeyboardButton::make(Button::EDIT),
                KeyboardButton::make(Button::LIST)
            )
            ->addRow(
                KeyboardButton::make(Button::BACK)
            );
    }

    public static function categoryManageMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make(Button::ADD),
                KeyboardButton::make(Button::DEL)
            )
            ->addRow(
                KeyboardButton::make(Button::EDIT),
                KeyboardButton::make(Button::LIST)
            )
            ->addRow(
                KeyboardButton::make(Button::BACK)
            );
    }

    public static function cancel(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make(Button::CANCEL)
            );
    }

    public static function back(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make(Button::BACK)
            );
    }

    public static function confirm(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make(Button::CONFIRM),
                KeyboardButton::make(Button::CANCEL)
            );
    }


    public static function remove(): ReplyKeyboardRemove
    {
        return ReplyKeyboardRemove::make(remove_keyboard: true);
    }

    public static function movieActions(array $movie, array $extra, bool $isAdmin = false): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if (($extra['video_count'] ?? 0) > 0) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "▶️ Tomosha qilish",
                    callback_data: "watch_movie_{$movie['id']}"
                )
            );
        }

        $likeText = ($extra['is_liked'] ?? false) ? "❤️ " . $movie['likes'] : "🤍 " . $movie['likes'];
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: "👁 " . $movie['views'],
                callback_data: "noop"
            ),
            InlineKeyboardButton::make(
                text: $likeText,
                callback_data: "like_movie_{$movie['id']}"
            )
        );

        if ($isAdmin) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "✏️ Tahrirlash",
                    callback_data: "edit_movie_{$movie['id']}"
                ),
                InlineKeyboardButton::make(
                    text: "🗑 O'chirish",
                    callback_data: "delete_movie_{$movie['id']}"
                )
            );
        }

        return $keyboard;
    }

    public static function movieEditActions(int $movieId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "✏️ Sarlavha",
                    callback_data: "edit_movie_title_{$movieId}"
                ),
                InlineKeyboardButton::make(
                    text: "✏️ Yil",
                    callback_data: "edit_movie_year_{$movieId}"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "✏️ Tavsif",
                    callback_data: "edit_movie_description_{$movieId}"
                ),
                InlineKeyboardButton::make(
                    text: "✏️ Rasm",
                    callback_data: "edit_movie_photo_{$movieId}"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "🎭 Janrlarni boshqarish",
                    callback_data: "manage_movie_categories_{$movieId}"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "📹 Video qismlar",
                    callback_data: "manage_videos_{$movieId}"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "🔙 Orqaga",
                    callback_data: "movie_{$movieId}"
                )
            );
    }

    public static function videoList(array $videos, int $movieId, bool $isAdmin = false): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($videos as $video) {
            $row = [
                InlineKeyboardButton::make(
                    text: "📹 {$video['part_number']}-qism",
                    callback_data: "play_video_{$video['id']}"
                )
            ];

            if ($isAdmin) {
                $row[] = InlineKeyboardButton::make(
                    text: "✏️",
                    callback_data: "edit_video_{$video['id']}"
                );
                $row[] = InlineKeyboardButton::make(
                    text: "🗑",
                    callback_data: "delete_video_{$video['id']}"
                );
            }

            $keyboard->addRow(...$row);
        }

        if ($isAdmin) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "➕ Video qo'shish",
                    callback_data: "add_video_{$movieId}"
                )
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: "🔙 Kinoga qaytish",
                callback_data: "movie_{$movieId}"
            )
        );

        return $keyboard;
    }

    public static function categoryList(array $categories, bool $isAdmin = false): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($categories as $category) {
            $row = [
                InlineKeyboardButton::make(
                    text: "🎭 {$category['name']}",
                    callback_data: "category_{$category['id']}"
                )
            ];

            if ($isAdmin) {
                $row[] = InlineKeyboardButton::make(
                    text: "✏️",
                    callback_data: "edit_category_{$category['id']}"
                );
                $row[] = InlineKeyboardButton::make(
                    text: "🗑",
                    callback_data: "delete_category_{$category['id']}"
                );
            }

            $keyboard->addRow(...$row);
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: "🔙 Orqaga",
                callback_data: "back_to_main"
            )
        );

        return $keyboard;
    }

    public static function channelActions(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "➕ Qo'shish",
                    callback_data: "add_channel"
                ),
                InlineKeyboardButton::make(
                    text: "🗑 O'chirish",
                    callback_data: "delete_channel"
                )
            );
    }

    public static function statisticsActions(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "🔄 Yangilash",
                    callback_data: "refresh_stats"
                )
            );
    }

    public static function confirmDelete(string $type, int $id): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "✅ Ha, o'chirish",
                    callback_data: "confirm_delete_{$type}_{$id}"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "🚫 Yo'q, bekor qilish",
                    callback_data: "cancel_delete_{$type}_{$id}"
                )
            );
    }

    public static function categorySelector(array $categories, array $selectedIds = []): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($categories as $category) {
            $isSelected = in_array($category['id'], $selectedIds);
            $prefix = $isSelected ? "✅ " : "";

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "{$prefix}{$category['name']}",
                    callback_data: "select_category_{$category['id']}"
                )
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: "✅ Saqlash",
                callback_data: "save_categories"
            ),
            InlineKeyboardButton::make(
                text: "🚫 Bekor qilish",
                callback_data: "cancel_select_categories"
            )
        );

        return $keyboard;
    }

    public static function subscriptionButtons(array $channels): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($channels as $channel) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "➡️ @{$channel['username']}",
                    url: "https://t.me/{$channel['username']}"
                )
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: "✅ Tekshirish",
                callback_data: "check_subscription"
            )
        );

        return $keyboard;
    }

    public static function getUrlButton(string $text, string $url): InlineKeyboardButton
    {
        return InlineKeyboardButton::make(text: $text, url: $url);
    }

    public static function getCallbackButton(string $text, string $callbackData): InlineKeyboardButton
    {
        return InlineKeyboardButton::make(text: $text, callback_data: $callbackData);
    }

    public static function getInlineKeyboard(array $buttons): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($buttons as $row) {
            $keyboard->addRow(...$row);
        }

        return $keyboard;
    }
}
