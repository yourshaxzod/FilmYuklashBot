<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\Channel;
use App\Helpers\{Keyboard, Menu, State, Validator, Formatter};
use PDO;

class ChannelService
{
    public static function showChannels(Nutgram $bot, PDO $db): void
    {
        if (!Validator::isAdmin($bot)) {
            Menu::showError($bot, "Sizda yetarli huquq yo'q!");
            return;
        }

        try {
            $channels = Channel::getAll($db);

            if (empty($channels)) {
                $bot->sendMessage(
                    text: "🔐 <b>Majburiy obuna kanallari:</b>\n\n" .
                        "Hozircha kanallar qo'shilmagan.",
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::ChannelActions()
                );
                return;
            }

            $message = "🔐 <b>Majburiy obuna kanallari:</b>\n\n";

            foreach ($channels as $index => $channel) {
                $status = $channel['is_active'] ? '✅ Faol' : '❌ Faol emas';
                $message .= ($index + 1) . ". @" . $channel['username'] . " - {$status}\n";
            }

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::ChannelActions()
            );
        } catch (\Exception $e) {
            Menu::showError($bot, "Kanallarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function addChannel(Nutgram $bot, PDO $db, string $username): void
    {
        if (!Validator::isAdmin($bot)) {
            Menu::showError($bot, "Sizda yetarli huquq yo'q!");
            return;
        }

        try {
            $username = ltrim($username, '@');

            $existing = Channel::findByUsername($db, $username);
            if ($existing) {
                Menu::showError($bot, "Bu kanal allaqachon qo'shilgan!");
                return;
            }

            try {
                $chat = $bot->getChat('@' . $username);

                if ($chat->type !== 'channel') {
                    Menu::showError($bot, "Bu kanal emas!");
                    return;
                }

                $botInfo = $bot->getMe();
                $chatMember = $bot->getChatMember(
                    chat_id: $chat->id,
                    user_id: $botInfo->id
                );

                if (!in_array($chatMember->status, ['creator', 'administrator'])) {
                    Menu::showError($bot, "Bot kanalda admin emas! Iltimos, botni kanalga admin qiling va qayta urinib ko'ring.");
                    return;
                }

                $channelData = [
                    'username' => $username,
                    'title' => $chat->title,
                    'chat_id' => $chat->id,
                    'is_active' => true
                ];

                Channel::create($db, $channelData);

                Menu::showSuccess($bot, "Kanal muvaffaqiyatli qo'shildi!");
            } catch (\Exception $e) {
                Menu::showError($bot, "Kanalni tekshirishda xatolik: Iltimos kanal mavjudligini va bot kanalda admin ekanligini tekshiring.");
            }
        } catch (\Exception $e) {
            Menu::showError($bot, "Kanal qo'shishda xatolik: " . $e->getMessage());
        }
    }

    public static function deleteChannel(Nutgram $bot, PDO $db, int $channelId): void
    {
        if (!Validator::isAdmin($bot)) {
            Menu::showError($bot, "Sizda yetarli huquq yo'q!");
            return;
        }

        try {
            $channel = Channel::find($db, $channelId);
            if (!$channel) {
                Menu::showError($bot, "Kanal topilmadi!");
                return;
            }

            Channel::delete($db, $channelId);
            Menu::showSuccess($bot, "Kanal muvaffaqiyatli o'chirildi!");
        } catch (\Exception $e) {
            Menu::showError($bot, "Kanalni o'chirishda xatolik: " . $e->getMessage());
        }
    }

    public static function toggleChannel(Nutgram $bot, PDO $db, int $channelId): void
    {
        if (!Validator::isAdmin($bot)) {
            Menu::showError($bot, "Sizda yetarli huquq yo'q!");
            return;
        }

        try {
            $isActive = Channel::toggleActive($db, $channelId);

            $statusText = $isActive ? "faollashtirildi" : "o'chirildi";
            Menu::showSuccess($bot, "Kanal muvaffaqiyatli {$statusText}!");
        } catch (\Exception $e) {
            Menu::showError($bot, "Kanal statusini o'zgartirishda xatolik: " . $e->getMessage());
        }
    }

    public static function showSubscriptionRequirement(Nutgram $bot, PDO $db): bool
    {
        try {
            $notSubscribed = Channel::checkUserSubscription($bot, $db, $bot->userId());

            if (empty($notSubscribed)) {
                return true;
            }

            $message = "📢 <b>Botdan foydalanish uchun quyidagi kanallarga obuna bo'ling:</b>\n\n";

            $buttons = [];
            foreach ($notSubscribed as $channel) {
                $message .= "• @{$channel['username']}\n";
                $buttons[] = [Keyboard::getUrlButton("➡️ @{$channel['username']}", "https://t.me/{$channel['username']}")];
            }

            $buttons[] = [Keyboard::getCallbackButton("✅ Tekshirish", "check_subscription")];

            $bot->sendMessage(
                text: $message,
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::getInlineKeyboard($buttons)
            );

            return false;
        } catch (\Exception $e) {
            return true;
        }
    }

    public static function checkSubscription(Nutgram $bot, PDO $db): bool
    {
        try {
            $notSubscribed = Channel::checkUserSubscription($bot, $db, $bot->userId());

            if (empty($notSubscribed)) {
                $bot->answerCallbackQuery(
                    text: "✅ Barcha kanallarga obuna bo'lgansiz!",
                    show_alert: true
                );

                Menu::showMainMenu($bot);

                return true;
            } else {
                $bot->answerCallbackQuery(
                    text: "❌ Siz hali barcha kanallarga obuna bo'lmagansiz!",
                    show_alert: true
                );
                return false;
            }
        } catch (\Exception $e) {
            $bot->answerCallbackQuery(
                text: "⚠️ Tekshirishda xatolik yuz berdi, qayta urinib ko'ring.",
                show_alert: true
            );
            return false;
        }
    }
}
