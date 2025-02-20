<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;

function createBot(): Nutgram
{
    $dotenv = Dotenv\Dotenv::createImmutable('../');
    $dotenv->load();

    $bot = new Nutgram($_ENV['BOT_TOKEN']);
    $bot->setRunningMode(Polling::class);

    return $bot;
}
