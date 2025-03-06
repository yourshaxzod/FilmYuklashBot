<?php

namespace App\Helpers;

class Text
{
    /**
     * Main menu welcome message
     */
    public static function mainMenu(): string
    {
        return "<b>👋 Assalomu alaykum, xush kelibsiz!</b>\n\n" .
            "🍿 <b>Bu botda eng so'nggi filmlar va seriallarni qidirib topishingiz mumkin.</b>";
    }

    /**
     * Admin panel welcome message
     */
    public static function adminMenu(): string
    {
        return "🛠 <b>Admin Panel</b>\n\n" .
            "Boshqaruv panelidan foydalanish uchun tugmalardan birini tanlang.";
    }

    /**
     * Search menu text
     */
    public static function searchMenu(): string
    {
        return "🔍 <b>Qidirish</b>\n\n" .
            "Iltimos, qidirmoqchi bo'lgan kino nomini yoki ID raqamini kiriting:";
    }

    /**
     * Categories menu text
     */
    public static function categoriesMenu(): string
    {
        return "🎭 <b>Janrlar</b>\n\n" .
            "Quyidagi janrlardan birini tanlang:";
    }

    /**
     * Movie management menu text
     */
    public static function movieManage(): string
    {
        return "🎬 <b>Kinolar boshqaruvi</b>\n\n" .
            "Kinolarni qo'shish, o'chirish yoki tahrirlash uchun tugmalardan foydalaning.";
    }

    /**
     * Category management menu text
     */
    public static function categoryManage(): string
    {
        return "🎭 <b>Janrlar boshqaruvi</b>\n\n" .
            "Janrlarni qo'shish, o'chirish yoki tahrirlash uchun tugmalardan foydalaning.";
    }

    /**
     * Add movie instruction
     */
    public static function addMovie(): string
    {
        return "🎬 <b>Kino qo'shish</b>\n\n" .
            "Yangi kino nomini kiriting:";
    }

    /**
     * Edit movie instruction
     */
    public static function editMovie(): string
    {
        return "✏️ <b>Kinoni tahrirlash</b>\n\n" .
            "Tahrirlash uchun kino ID raqamini kiriting:";
    }

    /**
     * Add category instruction
     */
    public static function addCategory(): string
    {
        return "🎭 <b>Janr qo'shish</b>\n\n" .
            "Yangi janr nomini kiriting:";
    }

    /**
     * Add video instruction
     */
    public static function addVideo(array $movie): string
    {
        return "📹 <b>Video qo'shish</b>\n\n" .
            "Kino: <b>{$movie['title']}</b>\n\n" .
            "Video nomini kiriting:";
    }

    /**
     * Add channel instruction
     */
    public static function addChannel(): string
    {
        return "📢 <b>Kanal qo'shish</b>\n\n" .
            "Kanal username'ini @username formatida kiriting:";
    }

    /**
     * Movie info display
     */
    public static function movieInfo(array $movie, int $videoCount, array $categories = [], bool $isAdmin = false): string
    {
        $text = "🎬 <b>{$movie['title']}</b>\n\n";
        $text .= "📅 <b>Yil:</b> {$movie['year']}\n";
        $text .= "📹 <b>Qismlar:</b> {$videoCount} ta\n";

        if (!empty($categories)) {
            $categoryNames = array_column($categories, 'name');
            $text .= "🎭 <b>Janrlar:</b> " . implode(', ', $categoryNames) . "\n";
        }

        $text .= "\n📝 <b>Tavsif:</b>\n{$movie['description']}";

        if ($isAdmin) {
            $text .= "\n\n🆔 <b>ID:</b> {$movie['id']}";
        }

        return $text;
    }

    /**
     * Video info display
     */
    public static function videoInfo(array $video): string
    {
        $text = "🎬 <b>Kino:</b> {$video['movie_title']}\n";
        $text .= "📹 <b>Video:</b> {$video['title']}\n";
        $text .= "🔢 <b>Qism:</b> {$video['part_number']}\n";

        return $text;
    }

    /**
     * Category info display
     */
    public static function categoryInfo(array $category, int $moviesCount): string
    {
        $text = "🎭 <b>Janr:</b> {$category['name']}\n\n";

        if (!empty($category['description'])) {
            $text .= "📝 <b>Tavsif:</b> {$category['description']}\n\n";
        }

        $text .= "🎬 <b>Kinolar soni:</b> {$moviesCount} ta";

        return $text;
    }

    /**
     * Error messages
     */
    public static function movieNotFound(): string
    {
        return "⚠️ Kino topilmadi.";
    }

    public static function videoNotFound(): string
    {
        return "⚠️ Video topilmadi.";
    }

    public static function categoryNotFound(): string
    {
        return "⚠️ Janr topilmadi.";
    }

    public static function noPermission(): string
    {
        return "⚠️ Sizda bu bo'limga kirish uchun ruxsat yo'q.";
    }

    public static function error(string $message, string $error = ""): string
    {
        if (empty($error)) {
            return "⚠️ " . $message;
        }
        return "⚠️ <b>" . $message . ":</b> " . $error;
    }

    /**
     * Statistics display
     */
    public static function statisticsInfo(array $stats): string
    {
        $text = "📊 <b>Bot Statistikasi</b>\n\n";

        $text .= "🎬 <b>Kinolar:</b> " . Formatter::formatNumber($stats['movies']) . "\n";
        $text .= "📹 <b>Videolar:</b> " . Formatter::formatNumber($stats['videos']) . "\n";
        $text .= "🎭 <b>Janrlar:</b> " . Formatter::formatNumber($stats['categories']) . "\n";
        $text .= "👤 <b>Foydalanuvchilar:</b> " . Formatter::formatNumber($stats['users']) . "\n";
        $text .= "👁 <b>Ko'rishlar:</b> " . Formatter::formatNumber($stats['views']) . "\n";
        $text .= "❤️ <b>Yoqtirishlar:</b> " . Formatter::formatNumber($stats['likes']) . "\n\n";

        $text .= "📅 <b>Bugun:</b>\n";
        $text .= "👤 Yangi foydalanuvchilar: " . Formatter::formatNumber($stats['today']['new_users']) . "\n";
        $text .= "👁 Ko'rishlar: " . Formatter::formatNumber($stats['today']['views']) . "\n";
        $text .= "❤️ Yoqtirishlar: " . Formatter::formatNumber($stats['today']['likes']) . "\n\n";

        if (isset($stats['top_movie']) && $stats['top_movie']) {
            $text .= "🔝 <b>Eng mashhur kino:</b>\n";
            $text .= "🎬 " . $stats['top_movie']['title'] . "\n";
            $text .= "👁 Ko'rishlar: " . Formatter::formatNumber($stats['top_movie']['views']) . "\n";
            $text .= "❤️ Yoqtirishlar: " . Formatter::formatNumber($stats['top_movie']['likes']) . "\n\n";
        }

        if (isset($stats['top_category']) && $stats['top_category']) {
            $text .= "🔝 <b>Eng mashhur janr:</b>\n";
            $text .= "🎭 " . $stats['top_category']['name'] . "\n";
            $text .= "🎬 Kinolar: " . Formatter::formatNumber($stats['top_category']['movies_count']) . "\n";
        }

        $text .= "\n⏱ <i>So'nggi yangilanish: " . date('d.m.Y H:i:s') . "</i>";

        return $text;
    }

    /**
     * Recommendations text
     */
    public static function recommendations(): string
    {
        return "⭐️ <b>Tavsiyalar</b>\n\n" .
            "Ko'rish va yoqtirish tarixingizga asoslangan maxsus tavsiyalar:";
    }

    /**
     * Channel subscription text
     */
    public static function channelSubscription(): string
    {
        return "📢 <b>Botdan foydalanish uchun quyidagi kanallarga obuna bo'ling:</b>\n\n";
    }

    /**
     * Broadcast message text
     */
    public static function broadcastMessage(): string
    {
        return "📬 <b>Foydalanuvchilarga xabar yuborish</b>\n\n" .
            "Yubormoqchi bo'lgan xabarni kiriting:";
    }

    /**
     * Broadcast confirmation text
     */
    public static function broadcastConfirm(string $text): string
    {
        return "📬 <b>Quyidagi xabarni yuborishni tasdiqlaysizmi?</b>\n\n" . $text;
    }

    /**
     * Max likes reached text
     */
    public static function maxLikesReached(int $maxLikes): string
    {
        return "⚠️ Siz maksimal yoqtirish chegarasiga ({$maxLikes} ta) yetdingiz. Boshqa kino yoqtirish uchun avval yoqtirganlaringizdan birini o'chirishingiz kerak.";
    }
}
