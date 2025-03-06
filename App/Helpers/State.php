<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;
use PDO;

class State
{
    public const STATE = 'state';
    public const SCREEN = 'screen';

    // USER
    public const MAIN = 'main';
    public const SEARCH = 'search';
    public const FAVORITES = 'favorites';
    public const TRENDING = 'trending';
    public const CATEGORY = 'category';
    public const RECOMMENDATIONS = 'recommendations';

    // ADM
    public const ADM_MAIN = 'adm_main';
    public const ADM_MOVIE = 'adm_movie';
    public const ADM_CHANNEL = 'adm_channel';
    public const ADM_CATEGORY = 'adm_category';
    public const ADM_BROADCAST = 'adm_broadcast';
    public const ADM_STATISTIC = 'adm_statistic';

    public static function get(Nutgram $bot, string $key = 'state')
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return null;

        $stmt = $db->prepare("SELECT data FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['data']) {
            return null;
        }

        $data = json_decode($result['data'], true) ?? [];
        return $data[$key] ?? null;
    }

    public static function set(Nutgram $bot, string $key, $value)
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return;

        $stmt = $db->prepare("SELECT data FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $data = [];
        if ($result && $result['data']) {
            $data = json_decode($result['data'], true) ?? [];
        }

        $data[$key] = $value;

        $stmt = $db->prepare("UPDATE users SET data = ? WHERE user_id = ?");
        $stmt->execute([json_encode($data), $userId]);
    }

    public static function clear(Nutgram $bot, ?array $keys = null)
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return;

        $stmt = $db->prepare("SELECT data FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['data']) {
            return;
        }

        $data = json_decode($result['data'], true) ?? [];

        if ($keys === null) {
            unset($data['state']);
        } else {
            foreach ($keys as $key) {
                unset($data[$key]);
            }
        }

        $stmt = $db->prepare("UPDATE users SET data = ? WHERE user_id = ?");
        $stmt->execute([json_encode($data), $userId]);
    }

    public static function clearAll(Nutgram $bot)
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return;

        $stmt = $db->prepare("UPDATE users SET data = NULL WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    public static function getState(Nutgram $bot)
    {
        return self::get($bot, 'state');
    }

    public static function setState(Nutgram $bot, $value)
    {
        self::set($bot, 'state', $value);
    }

    public static function getScreen(Nutgram $bot)
    {
        return self::get($bot, self::SCREEN);
    }

    public static function setScreen(Nutgram $bot, string $screen)
    {
        self::set($bot, self::SCREEN, $screen);
    }


    public static function getUserData(Nutgram $bot)
    {
        return $bot->getUserData('data') ?? [];
    }

    public static function startProcess(Nutgram $bot, string $process, array $data = [])
    {
        self::set($bot, 'process', $process);
        self::set($bot, 'process_data', $data);
        self::set($bot, 'process_step', 0);
    }

    public static function getProcessData(Nutgram $bot)
    {
        return self::get($bot, 'process_data') ?? [];
    }

    public static function updateProcessData(Nutgram $bot, array $data)
    {
        $currentData = self::getProcessData($bot);
        self::set($bot, 'process_data', array_merge($currentData, $data));
    }

    public static function getProcessStep(Nutgram $bot)
    {
        return self::get($bot, 'process_step') ?? 0;
    }

    public static function setProcessStep(Nutgram $bot, int $step)
    {
        self::set($bot, 'process_step', $step);
    }

    public static function nextProcessStep(Nutgram $bot)
    {
        $step = self::getProcessStep($bot) + 1;
        self::setProcessStep($bot, $step);
        return $step;
    }

    public static function endProcess(Nutgram $bot)
    {
        self::clear($bot, ['process', 'process_data', 'process_step']);
    }

    public static function isMovieCreation(Nutgram $bot)
    {
        $state = self::getState($bot);
        return $state === 'add_movie_confirm' || $state === 'add_movie_photo';
    }

    public static function isInState(Nutgram $bot, string|array $states)
    {
        $currentState = self::getState($bot);
        if (is_array($states)) {
            return in_array($currentState, $states);
        }
        return $currentState === $states;
    }
}
