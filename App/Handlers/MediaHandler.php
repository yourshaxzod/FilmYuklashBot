<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, VideoService};
use App\Helpers\Validator;
use PDO;

class MediaHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        // Photo handler
        $bot->onPhoto(function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) return;

            $state = $bot->getUserData('state');
            if ($state === 'add_movie_photo') {
                MovieService::handleAddPhoto($bot, $db);
            }
        });

        // Video handler
        $bot->onVideo(function (Nutgram $bot) use ($db) {
            if (!Validator::isAdmin($bot)) return;

            $state = $bot->getUserData('state');
            if ($state === 'add_video_file') {
                VideoService::handleAddFile($bot, $db);
            }
        });
    }
}
