<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video};
use App\Helpers\{Formatter, Keyboard, StateHandler, Validator, Menu, State, Text};
use PDO;

class MovieService
{
    public static function search(Nutgram $bot, PDO $db, string $query): void
    {
        try {
            $query = trim($query);

            if (Formatter::isNumericString($query)) {
                $movie = Movie::findByCode($db, Formatter::stringToInteger($query), $bot->userId());
                if ($movie) {
                    self::show($bot, $db, $movie);
                    return;
                }
            }

            $movies = Movie::search($db, $query, $bot->userId());
            if (empty($movies)) {
                $bot->sendMessage(
                    text: "Topilmadi",
                    reply_markup: Keyboard::MainMenu($bot)
                );
                State::clearState($bot);
                return;
            }

            if (count($movies) === 1) {
                self::show($bot, $db, $movies[0]);
                return;
            }

            $bot->sendMessage(
                text: "topildi",
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::searchResults($movies)
            );
        } catch (\Exception $e) {
            $bot->sendMessage(
                text: "⚠️ Qidirishda xatolik yuz berdi: " . $e->getMessage(),
                reply_markup: Keyboard::MainMenu($bot)
            );
        }
    }

    public static function show(Nutgram $bot, PDO $db, array|int $movie): void
    {
        try {
            if (is_int($movie)) {
                $movie = Movie::find($db, $movie, $bot->userId());
                if (!$movie) {
                    throw new \Exception("Kino topilmadi.");
                }
            }

            Movie::addView($db, $bot->userId(), $movie['id']);

            $videos = Video::getAllByMovie($db, $movie['id']);

            if ($movie['photo_file_id']) {
                $bot->sendPhoto(
                    photo: $movie['photo_file_id'],
                    caption: Text::MovieInfo($movie, Validator::isAdmin($bot)),
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::movieActions($movie, $videos, Validator::isAdmin($bot))
                );
            } else {
                $bot->sendMessage(
                    text: Text::MovieInfo($movie, Validator::isAdmin($bot)),
                    parse_mode: ParseMode::HTML,
                    reply_markup: Keyboard::movieActions($movie['id'], $movie, Validator::isAdmin($bot))
                );
            }
        } catch (\Exception $e) {
            $bot->sendMessage(text: "⚠️ Xatolik: " . $e->getMessage());
        }
    }
}
