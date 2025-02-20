<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, VideoService, ChannelService};
use App\Helpers\Validator;
use PDO;

class CallbackHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        // Movie callbacks
        $bot->onCallbackQueryData('movie_{id}', function (Nutgram $bot, $id) use ($db) {
            MovieService::handleCallback($bot, $db, (int)$id);
        });

        $bot->onCallbackQueryData('watch_movie_{id}', function (Nutgram $bot, $id) use ($db) {
            MovieService::handleWatch($bot, $db, (int)$id);
        });

        // Video callbacks
        $bot->onCallbackQueryData('play_video_{id}', function (Nutgram $bot, $id) use ($db) {
            VideoService::handlePlay($bot, $db, (int)$id);
        });

        // Admin callbacks
        $bot->onCallbackQueryData('delete_movie_{id}', function (Nutgram $bot, $id) use ($db) {
            if (!Validator::isAdmin($bot)) return;
            MovieService::handleDelete($bot, $db, (int)$id);
        });

        // ... other callback handlers
    }
}
