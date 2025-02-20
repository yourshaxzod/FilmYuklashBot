<?php

namespace App\Handlers;

use SergiX44\Nutgram\Nutgram;
use App\Services\{MovieService, ChannelService, VideoService};
use App\Helpers\Validator;
use PDO;

class MessageHandler
{
    public static function register(Nutgram $bot, PDO $db): void
    {
        $bot->onMessage(function (Nutgram $bot) use ($db) {
            $message = $bot->message()->text;
            $state = $bot->getUserData('state');

            // Handle cancellation
            if ($message === "🚫 Bekor qilish" || $message === "◀️ Kino panelga qaytish") {
                StateHandler::handleCancel($bot);
                return;
            }

            // Handle states
            switch ($state) {
                case 'search_movie':
                    MovieService::search($bot, $db, $message);
                    break;
                case 'add_movie_name':
                    if (!Validator::isAdmin($bot)) return;
                    MovieService::handleAddName($bot, $message);
                    break;
                case 'add_movie_code':
                    if (!Validator::isAdmin($bot)) return;
                    MovieService::handleAddCode($bot, $message);
                    break;
                    // ... other state handlers
            }
        });
    }
}
