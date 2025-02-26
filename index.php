<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bot.php';
require_once __DIR__ . '/config/database.php';

use App\Handlers\{CommandHandler, MessageHandler, CallbackHandler, MediaHandler};
use App\Helpers\Config;
use SergiX44\Nutgram\Nutgram;

Config::load();

try {
    $bot = createBot();
    $db = connectDatabase();

    CommandHandler::register($bot, $db);
    MessageHandler::register($bot, $db);
    CallbackHandler::register($bot, $db);
    MediaHandler::register($bot, $db);

    $bot->onException(function (Nutgram $bot, \Throwable $exception) {
        if (Config::isDebugMode()) {
            error_log("Bot Error: " . $exception->getMessage());
            error_log("Stack trace: " . $exception->getTraceAsString());
        }

        $bot->sendMessage(
            text: "⚠️ Xatolik yuz berdi. Iltimos keyinroq qayta urinib ko'ring.",
            reply_to_message_id: $bot->message()?->message_id
        );
    });

    $bot->run();
} catch (\Throwable $e) {
    if (Config::isDebugMode()) {
        error_log("Critical Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }

    die("Botni ishga tushirishda xatolik." . $e);
}
