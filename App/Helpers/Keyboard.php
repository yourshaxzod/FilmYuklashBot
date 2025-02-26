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
    public static function MainMenu(Nutgram $bot): ReplyKeyboardMarkup
    {
        $keyboard = ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('🔍 Qidirish'),
                KeyboardButton::make('❤️ Sevimlilar'),
                KeyboardButton::make('🔥 Trendlar'),
            );

        if (Validator::isAdmin($bot)) {
            $keyboard->addRow(
                KeyboardButton::make('🛠 Statistika va Boshqaruv')
            );
        }

        return $keyboard;
    }

    public static function AdminMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('🎬 Kinolar'),
                KeyboardButton::make('🔐 Kanallar')
            )
            ->addRow(
                KeyboardButton::make('📊 Statistika'),
                KeyboardButton::make('📬 Habarlar')
            )
            ->addRow(
                KeyboardButton::make('↩️ Ortga qaytish')
            );
    }

    public static function MovieManageMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make("➕ Kino qo'shish"),
                KeyboardButton::make("➖ Kino o'chirish"),
            )
            ->addRow(
                KeyboardButton::make("✏️ Tahrirlash"),
                KeyboardButton::make("📋 Ro'yxat")
            )
            ->addRow(
                KeyboardButton::make('◀️ Admin panelga qaytish')
            );
    }

    public static function Cancel(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('🚫 Bekor qilish')
            );
    }

    public static function BackMain(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('↩️ Ortga qaytish')
            );
    }

    public static function Confirm(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('✅ Tasdiqlash'),
                KeyboardButton::make('🚫 Bekor qilish')
            );
    }

    public static function Remove(): ReplyKeyboardRemove
    {
        return ReplyKeyboardRemove::make(true);
    }

    public static function MovieActions(array $movie, array $video, bool $isAdmin = false): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if (($video['video_count'] ?? 0) > 0) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '▶️ Ko\'rish',
                    callback_data: "watch_movie_{$movie['id']}"
                )
            );
        }

        $likeText = ($video['is_liked'] ?? false) ? "❤️ " . $movie['likes'] : "🤍 " . $movie['likes'];
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: "👁 " . $movie['views'],
                callback_data: "like_movie_{$movie['id']}"
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

    public static function MovieEditActions(int $movieId): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "✏️ Sarlavha",
                    callback_data: "edit_movie_title_{$movieId}"
                ),
                InlineKeyboardButton::make(
                    text: "✏️ Kod",
                    callback_data: "edit_movie_code_{$movieId}"
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
                    text: "📹 Video qismlar",
                    callback_data: "manage_videos_{$movieId}"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "🔙 Orqaga",
                    callback_data: "back_to_movie_menu"
                )
            );
    }

    public static function MovieList(array $movies): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($movies as $movie) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "📹 {$movie['title']}",
                    callback_data: "play_video_{$movie['id']}"
                )
            );
        }

        return $keyboard;
    }

    public static function VideoList(array $videos, int $movieId): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($videos as $video) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: "📹 {$video['part_number']}-qism - {$video['title']}",
                    callback_data: "play_video_{$video['id']}"
                )
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🔙 Orqaga',
                callback_data: "movie_{$movieId}"
            )
        );

        return $keyboard;
    }

    public static function ChannelActions(): InlineKeyboardMarkup
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

    public static function Pagination(
        string $action,
        int $currentPage,
        int $totalPages,
        ?array $additionalButtons = null
    ): InlineKeyboardMarkup {
        $keyboard = InlineKeyboardMarkup::make();

        if ($additionalButtons) {
            foreach ($additionalButtons as $row) {
                $keyboard->addRow(...$row);
            }
        }

        $paginationRow = [];

        if ($currentPage > 1) {
            $paginationRow[] = InlineKeyboardButton::make(
                text: '⬅️',
                callback_data: "{$action}_page_" . ($currentPage - 1)
            );
        }

        $paginationRow[] = InlineKeyboardButton::make(
            text: "{$currentPage} / {$totalPages}",
            callback_data: 'noop'
        );

        if ($currentPage < $totalPages) {
            $paginationRow[] = InlineKeyboardButton::make(
                text: '➡️',
                callback_data: "{$action}_page_" . ($currentPage + 1)
            );
        }

        $keyboard->addRow(...$paginationRow);

        return $keyboard;
    }

    public static function StatActions(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "🔄 Yangilash",
                    callback_data: "refresh_stats"
                )
            );
    }

    public static function ConfirmDelete(string $type, int $id): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "✅ Ha",
                    callback_data: "confirm_delete_{$type}_{$id}"
                ),
                InlineKeyboardButton::make(
                    text: "🚫 Yo'q",
                    callback_data: "cancel_delete_{$type}_{$id}"
                )
            );
    }

    public static function SearchResults(array $movies): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($movies as $movie) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: $movie['title'],
                    callback_data: "movie_{$movie['id']}"
                )
            );
        }

        return $keyboard;
    }

    private static function getUrlButton(string $text, string $url): InlineKeyboardButton
    {
        return InlineKeyboardButton::make(text: $text, url: $url);
    }

    private static function getCallbackButton(string $text, string $callbackData): InlineKeyboardButton
    {
        return InlineKeyboardButton::make(text: $text, callback_data: $callbackData);
    }
}
