<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, ChannelService};
use App\Helpers\{Keyboard, Validator};
use PDO;

class CommandHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        // Start command
        $bot->onCommand('start', function (Nutgram $bot) use ($db) {
            $bot->setUserData('state', null);
            $bot->setUserData('movie_data', null);
            $bot->setUserData('video_data', null);
            Keyboard::showMainMenu($bot);
        });

        // Menu commands
        $bot->onText('🔍 Kino qidirish', function (Nutgram $bot) {
            MovieService::startSearch($bot);
        });

        $bot->onText('🏆 TOP', function (Nutgram $bot) use ($db) {
            MovieService::showTop($bot, $db);
        });

        // Admin commands
        $bot->onText('🎛 Admin panel', function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) return;
            Keyboard::showAdminMenu($bot);
        });

        // Kinolar menu commands
        $bot->onText('🎬 Kinolar', function (Nutgram $bot) {
            if (!Validator::isAdmin($bot)) return;
            Keyboard::showManageMovieMenu($bot);
        });

        // ...and other command handlers
    }
}