<?php

namespace App\Middlewares;

use SergiX44\Nutgram\Nutgram;
use App\Models\User;
use PDO;

class UserMiddleware
{
    public static function register(Nutgram $bot, PDO $db)
    {
        $bot->middleware(function (Nutgram $bot, $next) use ($db) {
            if ($bot->userId()) {
                User::register($db, $bot);
            }

            $next($bot);
        });
    }
}
