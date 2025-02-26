<?php

declare(strict_types=1);

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;
use App\Helpers\Config;

function createBot(): Nutgram
{
    try {
        Config::load();

        $token = Config::get('BOT_TOKEN');
        if (empty($token)) {
            throw new \Exception('Bot token kiritilmagan!');
        }

        $bot = new Nutgram($token);

        $bot->setRunningMode(Polling::class);

        return $bot;
    } catch (\Throwable $e) {
        throw new \Exception('Botni yaratishda xatolik:: ' . $e->getMessage());
    }
}
