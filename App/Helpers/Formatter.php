<?php

namespace App\Helpers;

class Formatter
{
    public static function movieInfo(array $movie): string
    {
        return "🎬 <b>Nomi:</b> {$movie['title']}\n" .
            "📝 <b>Tavsif:</b> {$movie['description']}\n" .
            "🔢 <b>Kod:</b> {$movie['code']}\n" .
            "👁 <b>Ko'rildi:</b> {$movie['views']} marta";
    }

    public static function searchPrompt(): string
    {
        return "🔍 <b>Iltimos, qidirmoqchi bo'lgan kino nomini yoki kodini kiriting:</b>";
    }

    public static function movieNotFound(): string
    {
        return "⚠️ Afsuski, siz qidirgan kino topilmadi.";
    }

    public static function videoAddSuccess(array $videoData): string
    {
        return "✅ Video muvaffaqiyatli qo'shildi!\n\n" .
            "Kino: {$videoData['movie_title']}\n" .
            "Video: {$videoData['title']}\n" .
            "Qism: {$videoData['part_number']}";
    }

    public static function channelList(array $channels): string
    {
        $message = "📢 Kanallar ro'yxati:\n\n";
        foreach ($channels as $channel) {
            $message .= "@{$channel['username']}\n";
        }
        return $message;
    }

    public static function statisticsInfo(array $data): string
    {
        $message = "📊 Bot statistikasi:\n\n" .
            "🎬 Kinolar soni: {$data['movies']}\n" .
            "📹 Video qismlar soni: {$data['videos']}\n" .
            "📢 Kanallar soni: {$data['channels']}\n";

        if ($data['top_movie']) {
            $message .= "\n🏆 <b>Eng ko'p ko'rilgan kino:</b>\n" .
                "• {$data['top_movie']['title']} ({$data['top_movie']['views']} marta)";
        }

        return $message;
    }
}
