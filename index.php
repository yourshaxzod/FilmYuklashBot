<?php

require_once './vendor/autoload.php';
require_once './config/bot.php';
require_once './config/database.php';

use App\Handlers\{CommandHandler, MessageHandler, CallbackHandler, MediaHandler};

$bot = createBot();
$db = connectDatabase();
$admins = adminsIds();

CommandHandler::register($bot, $db);
MessageHandler::register($bot, $db);
CallbackHandler::register($bot, $db);
MediaHandler::register($bot, $db);

$bot->run();