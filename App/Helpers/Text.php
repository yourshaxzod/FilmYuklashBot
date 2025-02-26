<?php

namespace App\Helpers;

class Text
{
    public static function MainMenu(): string
    {
        return "<b>🎬 Salom, Kino Ishqibozi!</b>\n\n" .
            "🍿 <b>Bu botda eng so'nggi filmlar va seriallarni qidirib topishingiz mumkin.</b>\n\n" .
            "📌 <b>Foydalanish bo'yicha:</b>\n" .
            "🔍 <b>Qidirish</b> - Kinolarni qidirish\n" .
            "❤️ <b>Sevimlilar</b> - Sevimli kinolaringiz\n" .
            "🔥 <b>Trendlar</b> - Mashhur filmlar\n\n" .
            "🎯 <b>Tugmalardan foydalaning va zavqlaning!</b>";
    }

    public static function AdminMenu(): string
    {
        return "🛠 <b>Admin Panel</b>\n\n" .
            "Quyidagi bo'limlardan birini tanlang:\n\n" .
            "🎬 <b>Kinolar</b> - Kinolarni boshqarish\n" .
            "🔐 <b>Kanallar</b> - Majburiy obuna kanallarini boshqarish\n" .
            "📊 <b>Statistika</b> - Bot statistikasini ko'rish\n\n" .
            "⚙️ <i>Kerakli bo'limni tanlang</i>";
    }

    public static function SearchMenu(): string
    {
        return "🔍 <b>Iltimos, qidirmoqchi bo'lgan kino nomini yoki kodini kiriting:</b>";
    }

    public static function MovieManage(): string
    {
        return "🎬 <b>Kinolarni boshqarish</b>\n\n" .
            "Quyidagi amallardan birini tanlang:\n\n" .
            "➕ <b>Kino qo'shish</b> - Yangi kino qo'shish\n" .
            "✏️ <b>Kinoni tahrirlash</b> - Mavjud kinoni tahrirlash\n" .
            "🗑 <b>Kinoni o'chirish</b> - Kinoni o'chirib tashlash\n" .
            "📋 <b>Kinolar ro'yxati</b> - Barcha kinolar ro'yxati\n" .
            "📹 <b>Video qismlar</b> - Kino video qismlarini boshqarish\n\n" .
            "⚙️ <i>Kerakli amalni tanlang</i>";
    }

    public static function AddMovie(): string
    {
        return "🎬 <b>Yangi kino qo'shish</b>\n\n" .
            "Quyidagi ma'lumotlarni ketma-ket kiriting:\n\n" .
            "1. Kino nomi\n" .
            "2. Kino kodi (unique)\n" .
            "3. Kino haqida tavsif\n" .
            "4. Kino rasmi (poster)\n" .
            "5. Video qismlari\n\n" .
            "❗️ Har bir bosqichda to'g'ri ma'lumot kiritilishi muhim\n" .
            "🚫 Bekor qilish uchun \"Bekor qilish\" tugmasini bosing";
    }

    public static function EditMovie(): string
    {
        return "✏️ <b>Kinoni tahrirlash</b>\n\n" .
            "Quyidagi ma'lumotlarni tahrirlay olasiz:\n\n" .
            "• Kino nomi\n" .
            "• Kino kodi\n" .
            "• Kino tavsifi\n" .
            "• Kino rasmi\n" .
            "• Video qismlar\n\n" .
            "❗️ Tahrirlash uchun kinoni tanlang";
    }

    public static function AddVideo($movie): string
    {
        return "📹 <b>Video qo'shish</b>\n\n" .
            "🎬 <b>Kino:</b> {$movie['title']}\n\n" .
            "Quyidagi ma'lumotlarni ketma-ket kiriting:\n\n" .
            "1. Video sarlavhasi\n" .
            "2. Qism raqami\n" .
            "3. Video fayl\n\n" .
            "❗️ Video hajmi 50MB dan oshmasligi kerak\n" .
            "❗️ Video formati: MP4, MKV, AVI\n" .
            "🚫 Bekor qilish uchun \"Bekor qilish\" tugmasini bosing";
    }

    public static function AddChannel(): string
    {
        return "📢 <b>Kanal qo'shish</b>\n\n" .
            "Kanal qo'shish uchun:\n\n" .
            "1. Bot kanalda admin bo'lishi kerak\n" .
            "2. Kanal username @ bilan kiriting\n" .
            "Masalan: @mychannel\n\n" .
            "❗️ Kanal public bo'lishi shart\n" .
            "🚫 Bekor qilish uchun \"Bekor qilish\" tugmasini bosing";
    }

    public static function MovieInfo(array $movie, bool $isAdmin = false): string
    {
        $text = "";
        $text .= "🆔 <b>ID:</b> {$movie['id']}\n";
        $text .= "🎬 <b>Nomi:</b> {$movie['title']}\n";
        $text .= "📝 <b>Tavsif:</b> {$movie['description']}\n";
        $text .= "📅 <b>Yil:</b> {$movie['year']}\n";

        return $text;
    }

    public static function VideoInfo(array $video, bool $isAdmin = false): string
    {
        $text = "";
        $text .= "🎬 <b>Nomi:</b> {$video['title']}\n\n";
        $text .= "🔢 <b>Kod:</b> {$video['code']}\n";

        $text .= "\n📝 <b>Tavsif:</b>\n{$video['description']}\n\n";

        return $text;
    }
}
