<?php

namespace App\Helpers;

class Text
{
    public static function mainMenu(): string
    {
        return "<b>👋 Assalomu alaykum, xush kelibsiz!</b>\n\n" .
            "🍿 <b>Bu botda eng so'nggi filmlar va seriallarni qidirib topishingiz mumkin.</b>";
    }

    public static function adminMenu(): string
    {
        return "🛠 <b>Admin Panel</b>\n\n";
    }

    public static function searchMenu(): string
    {
        return "🔍 <b>Iltimos, qidirmoqchi bo'lgan kino nomini yoki ID raqamini kiriting:</b>";
    }

    public static function categoriesMenu(): string
    {
        return "🏷 <b>Kategoriyalardan birini tanlang:</b>";
    }

    public static function movieManage(): string
    {
        return "🎬 <b>Kinolar.</b>";
    }

    public static function categoryManage(): string
    {
        return "🎭 <b>Janrlar</b>";
    }

    public static function addMovie(): string
    {
        return "🎬 <b>Kino nomini yozib yuboring.</b>";
    }

    public static function editMovie(): string
    {
        return "✏️ <b>Tahrirlash uchun kino ID raqamini kiriting.</b>";
    }

    public static function addCategory(): string
    {
        return "🎭 <b>Yangi janr nomini yozib yuboring.</b>";
    }

    public static function addVideo(array $movie): string
    {
        return "📹 <b>Video nomini yozib yuboring.</b>";
    }

    public static function addChannel(): string
    {
        return "📢 <b>Kanal username'ni yozib yuboring.</b>";
    }

    public static function movieInfo(array $movie, int $videoCount, array $categories = [], bool $isAdmin = false): string
    {
        $text = "🎬 <b>{$movie['title']}</b>\n\n";
        $text .= "🆔 <b>ID:</b> {$movie['id']}\n";
        $text .= "📅 <b>Yil:</b> {$movie['year']}\n";
        $text .= "📹 <b>Qismlar:</b> " . $videoCount . " ta\n";

        if (!empty($categories)) {
            $categoryNames = array_column($categories, 'name');
            $text .= "🏷 <b>Kategoriyalar:</b> " . implode(', ', $categoryNames) . "\n";
        }

        $text .= "\n📝 <b>Tavsif:</b>\n{$movie['description']}\n";

        return $text;
    }

    public static function videoInfo(array $video, bool $isAdmin = false): string
    {
        $text = "🎬 <b>Kino:</b> {$video['movie_title']}\n";
        $text .= "📹 <b>Video:</b> {$video['title']}\n";
        $text .= "🔢 <b>Qism:</b> {$video['part_number']}\n";

        return $text;
    }

    public static function categoryInfo(array $category, int $moviesCount, bool $isAdmin = false): string
    {
        $text = "🏷 <b>Kategoriya:</b> {$category['name']}\n\n";

        if (!empty($category['description'])) {
            $text .= "📝 <b>Tavsif:</b> {$category['description']}\n\n";
        }

        $text .= "🎬 <b>Kinolar soni:</b> {$moviesCount}\n";

        if ($isAdmin) {
            $text .= "\n🆔 <b>ID:</b> {$category['id']}\n";
            $text .= "🔗 <b>Slug:</b> {$category['slug']}\n";
        }

        return $text;
    }

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
        return "⚠️ Kategoriya topilmadi.";
    }

    public static function noPermission(): string
    {
        return "⚠️ Sizda bu bo'limga kirish uchun ruxsat yo'q.";
    }

    public static function error(string $message, string $error): string
    {
        return "⚠️ <b>" . $message . ":</b> " . $error;
    }

    public static function statisticsInfo(array $stats): string
    {
        $text = "📊 <b>Bot Statistikasi</b>\n\n";

        $text .= "🎬 <b>Kinolar:</b> " . Formatter::formatNumber($stats['movies']) . "\n";
        $text .= "📹 <b>Videolar:</b> " . Formatter::formatNumber($stats['videos']) . "\n";
        $text .= "🏷 <b>Kategoriyalar:</b> " . Formatter::formatNumber($stats['categories']) . "\n";
        $text .= "👤 <b>Foydalanuvchilar:</b> " . Formatter::formatNumber($stats['users']) . "\n";
        $text .= "👁 <b>Ko'rishlar:</b> " . Formatter::formatNumber($stats['views']) . "\n";
        $text .= "❤️ <b>Yoqtirishlar:</b> " . Formatter::formatNumber($stats['likes']) . "\n\n";

        $text .= "📅 <b>Bugun:</b>\n";
        $text .= " - 👤 Yangi foydalanuvchilar: " . Formatter::formatNumber($stats['today']['new_users']) . "\n";
        $text .= " - 👁 Ko'rishlar: " . Formatter::formatNumber($stats['today']['views']) . "\n";
        $text .= " - ❤️ Yoqtirishlar: " . Formatter::formatNumber($stats['today']['likes']) . "\n\n";

        $text .= "📆 <b>So'nggi 7 kun:</b>\n";
        $text .= " - 👤 Yangi foydalanuvchilar: " . Formatter::formatNumber($stats['week']['new_users']) . "\n";
        $text .= " - 👁 Ko'rishlar: " . Formatter::formatNumber($stats['week']['views']) . "\n";
        $text .= " - ❤️ Yoqtirishlar: " . Formatter::formatNumber($stats['week']['likes']) . "\n\n";

        if (isset($stats['top_movie']) && $stats['top_movie']) {
            $text .= "🔝 <b>Eng mashhur kino:</b>\n";
            $text .= " - 🎬 " . $stats['top_movie']['title'] . "\n";
            $text .= " - 👁 Ko'rishlar: " . Formatter::formatNumber($stats['top_movie']['views']) . "\n";
            $text .= " - ❤️ Yoqtirishlar: " . Formatter::formatNumber($stats['top_movie']['likes']) . "\n\n";
        }

        if (isset($stats['top_category']) && $stats['top_category']) {
            $text .= "🔝 <b>Eng mashhur kategoriya:</b>\n";
            $text .= " - 🏷 " . $stats['top_category']['name'] . "\n";
            $text .= " - 🎬 Kinolar: " . Formatter::formatNumber($stats['top_category']['movies_count']) . "\n";
            $text .= " - 👁 Ko'rishlar: " . Formatter::formatNumber($stats['top_category']['views']) . "\n\n";
        }

        $text .= "⏱ <i>So'nggi yangilanish: " . date('d.m.Y H:i:s') . "</i>";

        return $text;
    }

    public static function recommendations(): string
    {
        return "💫 <b>Sizga maxsus tavsiyalar</b>\n\n" .
            "Ko'rish va yoqtirish tarixingiz asosida siz uchun maxsus tanlangan kinolar:";
    }

    public static function channelSubscription(): string
    {
        return "📢 <b>Botdan foydalanish uchun quyidagi kanallarga obuna bo'ling:</b>\n\n";
    }

    public static function subscriptionSuccess(): string
    {
        return "✅ <b>Barcha kanallarga obuna bo'lgansiz!</b>\n\n" .
            "Endi botdan to'liq foydalanishingiz mumkin.";
    }

    public static function subscriptionFailed(): string
    {
        return "❌ <b>Siz hali barcha kanallarga obuna bo'lmagansiz!</b>\n\n" .
            "Iltimos, yuqoridagi barcha kanallarga obuna bo'lib, qayta tekshiring.";
    }

    public static function broadcastMessage(): string
    {
        return "📬 <b>Foydalanuvchilarga xabar yuborish</b>\n\n" .
            "Yubormoqchi bo'lgan xabarni kiriting:";
    }

    public static function broadcastConfirm(string $text): string
    {
        return "📬 <b>Quyidagi xabarni yuborishni tasdiqlaysizmi?</b>\n\n" . $text;
    }
}
