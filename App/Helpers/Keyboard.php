<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\{InlineKeyboardButton, InlineKeyboardMarkup, KeyboardButton, ReplyKeyboardMarkup};

class Keyboard {
    public static function showMainMenu(Nutgram $bot, array $adminIds) {
        $keyboard = ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('🔍 Kino qidirish'),
                KeyboardButton::make('🏆 TOP')
            );

        if (Validator::isAdmin($bot)) {
            $keyboard->addRow(KeyboardButton::make('🎛 Admin panel'));
        }

        return $keyboard;
    }

    public static function showAdminMenu(): ReplyKeyboardMarkup {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(KeyboardButton::make('🎬 Kinolar'), KeyboardButton::make('🔐 Kanallar'))
            ->addRow(KeyboardButton::make('📊 Statistika'), KeyboardButton::make('◀️ Orqaga'));
    }

    public static function showMovieMenu(): ReplyKeyboardMarkup {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(KeyboardButton::make("➕ Kino qo'shish"), KeyboardButton::make("✏️ Kinoni tahrirlash"))
            ->addRow(KeyboardButton::make("🗑 Kinoni o'chirish"), KeyboardButton::make('📋 Kinolar ro\'yxati'))
            ->addRow(KeyboardButton::make('📹 Kino video qismlarini boshqarish'))
            ->addRow(KeyboardButton::make('◀️ Admin panelga qaytish'));
    }

    public static function showChannelMenu(): ReplyKeyboardMarkup {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(KeyboardButton::make("➕ Kanal qo'shish"), KeyboardButton::make("📋 Kanallar ro'yxati"))
            ->addRow(KeyboardButton::make("🗑 Kanalni o'chirish"))
            ->addRow(KeyboardButton::make("◀️ Admin panelga qaytish"));
    }

    public static function movieActions(int $movieId, int $videoCount): InlineKeyboardMarkup {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    $videoCount > 0 ? '▶️ Ko\'rish' : '⚠️ Video mavjud emas',
                    callback_data: $videoCount > 0 ? "watch_movie_{$movieId}" : "no_videos"
                )
            );
    }

    public static function videoList(array $videos, int $movieId): InlineKeyboardMarkup {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($videos as $video) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "📹 {$video['part_number']}-qism",
                    callback_data: "play_video_{$video['id']}"
                )
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('🔙 Orqaga', callback_data: "movie_{$movieId}")
        );

        return $keyboard;
    }

    public static function movieEditMenu(int $movieId): InlineKeyboardMarkup {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make("✏️ Sarlavha", callback_data: "edit_movie_title_{$movieId}"),
                InlineKeyboardButton::make("✏️ Kod", callback_data: "edit_movie_code_{$movieId}")
            )
            ->addRow(
                InlineKeyboardButton::make("✏️ Tavsif", callback_data: "edit_movie_description_{$movieId}"),
                InlineKeyboardButton::make("✏️ Rasm", callback_data: "edit_movie_photo_{$movieId}")
            )
            ->addRow(
                InlineKeyboardButton::make("◀️ Orqaga", callback_data: "back_to_movie_menu")
            );
    }

    public static function confirmDelete(string $type, int $id): InlineKeyboardMarkup {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make("✅ Ha", callback_data: "confirm_delete_{$type}_{$id}"),
                InlineKeyboardButton::make("❌ Yo'q", callback_data: "cancel_delete_{$type}")
            );
    }

    public static function paginationButtons(string $action, int $currentPage, int $totalPages): array {
        $buttons = [];
        
        if ($currentPage > 1) {
            $buttons[] = InlineKeyboardButton::make(
                '⬅️',
                callback_data: "{$action}_page_" . ($currentPage - 1)
            );
        }
        
        $buttons[] = InlineKeyboardButton::make(
            "{$currentPage} / {$totalPages}",
            callback_data: 'noop'
        );
        
        if ($currentPage < $totalPages) {
            $buttons[] = InlineKeyboardButton::make(
                '➡️',
                callback_data: "{$action}_page_" . ($currentPage + 1)
            );
        }
        
        return $buttons;
    }
}