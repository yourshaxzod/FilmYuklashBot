<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use App\Models\Channel;
use App\Helpers\{Formatter, Keyboard};

class ChannelService
{
    public static function showList(Nutgram $bot, PDO $db): void
    {
        $channels = Channel::getAll($db);
        if (empty($channels)) {
            $bot->sendMessage(text: "📢 Hozircha kanallar mavjud emas.");
            return;
        }

        $bot->sendMessage(
            text: Formatter::channelList($channels),
            reply_markup: Keyboard::channelActions()
        );
    }

    public static function handleAdd(Nutgram $bot, PDO $db, string $username): void
    {
        if (!preg_match('/^@[a-zA-Z0-9_]+$/', $username)) {
            $bot->sendMessage("⚠️ Noto'g'ri format. Kanal usernameini @username formatida kiriting.");
            return;
        }

        try {
            Channel::create($db, substr($username, 1), $username);
            $bot->sendMessage(text: "✅ Kanal muvaffaqiyatli qo'shildi!");
            self::showList($bot, $db);
        } catch (Exception $e) {
            $bot->sendMessage("⚠️ Xatolik yuz berdi. Qayta urinib ko'ring.");
        }
    }

    // ... other channel related methods
}
