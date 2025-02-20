<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use App\Models\Movie;
use App\Helpers\{Formatter, Keyboard};
use PDO;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class MovieService {
    public static function startSearch(Nutgram $bot): void {
        $bot->sendMessage(
            text: Formatter::searchPrompt(),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboard::searchCancelButton()
        );
        $bot->setUserData('state', 'search_movie');
    }

    public static function search(Nutgram $bot, PDO $db, string $query): void {
        $movie = Movie::findByCode($db, $query);
        if ($movie) {
            self::showMovie($bot, $db, $movie);
            Movie::incrementViews($db, $movie['id']);
            return;
        }

        $movies = Movie::searchByTitle($db, $query);
        if (!empty($movies)) {
            if (count($movies) === 1) {
                self::showMovie($bot, $db, $movies[0]);
                Movie::incrementViews($db, $movies[0]['id']);
            } else {
                self::showSearchResults($bot, $movies);
            }
        } else {
            $bot->sendMessage(text: Formatter::movieNotFound());
        }
    }

    public static function showMovie(Nutgram $bot, PDO $db, array $movie): void {
        $videoCount = Movie::getVideoCount($db, $movie['id']);
        
        $bot->sendPhoto(
            photo: $movie['photo_file_id'],
            caption: Formatter::movieInfo($movie),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboard::movieActions($movie['id'], $videoCount)
        );
    }

    // ...other movie related methods
}