<?php

declare(strict_types=1);

date_default_timezone_set("Asia/Tashkent");

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/Config/bot.php";
require_once __DIR__ . "/Config/database.php";

use App\Handlers\{
    CommandHandler,
    MessageHandler,
    CallbackHandler,
    MediaHandler
};
use App\Helpers\Config;
use App\Services\{TavsiyaService, CacheService};
use SergiX44\Nutgram\Nutgram;

Config::load();

try {
    $bot = createBot();

    $db = connectDatabase();

    if (Config::isDebugMode()) {
        CacheService::clearAllCache();
    }

    if (Config::get("ENABLE_RECOMMENDATIONS", true)) {
        TavsiyaService::init($db);
    }

    CommandHandler::register($bot, $db);
    MessageHandler::register($bot, $db);
    CallbackHandler::register($bot, $db);
    MediaHandler::register($bot, $db);

    $bot->onException(function (Nutgram $bot, \Throwable $exception) {
        if (Config::isDebugMode()) {
            error_log("Bot Error: " . $exception->getMessage());
            error_log("Stack trace: " . $exception->getTraceAsString());

            if (
                $bot->userId() &&
                in_array($bot->userId(), Config::getAdminIds())
            ) {
                $bot->sendMessage(
                    text: "⚠️ <b>Xatolik:</b>\n<code>" .
                        htmlspecialchars($exception->getMessage()) .
                        "</code>\n\n<b>File:</b> " .
                        $exception->getFile() .
                        ":" .
                        $exception->getLine(),
                    parse_mode: "HTML"
                );
            }
        }

        if ($bot->userId()) {
            try {
                $bot->sendMessage(
                    text: "⚠️ Xatolik yuz berdi. Iltimos keyinroq qayta urinib ko'ring.",
                    reply_to_message_id: $bot->message()?->message_id
                );
            } catch (\Throwable $e) {
                error_log("Unable to send error message: " . $e->getMessage());
            }
        }
    });

    if (Config::isDebugMode()) {
        error_log("Bot started at: " . date("Y-m-d H:i:s"));
    }

    $bot->run();
} catch (\Throwable $e) {
    if (Config::isDebugMode()) {
        error_log("Critical Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }

    error_log("Bot initialization error: " . $e->getMessage());

    die("Botni ishga tushirishda xatolik yuz berdi.");
}
