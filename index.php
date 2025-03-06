<?php
date_default_timezone_set("Asia/Tashkent");

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/Config/bot.php";
require_once __DIR__ . "/Config/database.php";

use App\Handlers\{CommandHandler, MessageHandler, CallbackHandler, MediaHandler};
use App\Helpers\Config;
use App\Middlewares\UserMiddleware;

Config::load();

try {
    $bot = createBot();

    $db = connectDatabase();

    UserMiddleware::register($bot, $db);
    CommandHandler::register($bot, $db);
    MessageHandler::register($bot, $db);
    CallbackHandler::register($bot, $db);
    MediaHandler::register($bot, $db);

    if (Config::isDebugMode()) {
        error_log("Bot started at: " . date("Y-m-d H:i:s"));
    }

    $bot->run();
} catch (\Throwable $e) {
    if (Config::isDebugMode()) {
        error_log("Critical Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
    die("Botni ishga tushirishda xatolik yuz berdi.");
}
