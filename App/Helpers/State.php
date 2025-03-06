<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;
use PDO;

class State
{
    public const STATE = 'state';
    public const SCREEN = 'screen';

    public const MAIN = 'main';
    public const SEARCH = 'search';
    public const FAVORITES = 'favorites';
    public const TRENDING = 'trending';
    public const CATEGORY = 'category';
    public const RECOMMENDATIONS = 'recommendations';

    public const ADM_MAIN = 'adm_main';
    public const ADM_MOVIE = 'adm_movie';
    public const ADM_CHANNEL = 'adm_channel';
    public const ADM_CATEGORY = 'adm_category';
    public const ADM_BROADCAST = 'adm_broadcast';
    public const ADM_STATISTIC = 'adm_statistic';

    public static function get(Nutgram $bot, string $key = 'state'): mixed
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return null;

        try {
            $stmt = $db->prepare("SELECT data FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || empty($result['data'])) {
                return null;
            }

            $data = json_decode($result['data'], true) ?? [];
            return $data[$key] ?? null;
        } catch (\Throwable $e) {
            if (Config::isDebugMode()) {
                error_log("Error getting state: " . $e->getMessage());
            }
            return null;
        }
    }

    public static function set(Nutgram $bot, string $key, mixed $value): bool
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return false;

        try {
            $stmt = $db->prepare("SELECT data FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $data = [];
            if ($result && !empty($result['data'])) {
                $data = json_decode($result['data'], true) ?? [];
            }

            $data[$key] = $value;

            $stmt = $db->prepare("UPDATE users SET data = ?, updated_at = NOW() WHERE user_id = ?");
            $success = $stmt->execute([json_encode($data), $userId]);

            return $success;
        } catch (\Throwable $e) {
            if (Config::isDebugMode()) {
                error_log("Error setting state: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function clear(Nutgram $bot, ?array $keys = null): bool
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return false;

        try {
            $stmt = $db->prepare("SELECT data FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || empty($result['data'])) {
                return true;
            }

            $data = json_decode($result['data'], true) ?? [];

            if ($keys === null) {
                unset($data['state']);
            } else {
                foreach ($keys as $key) {
                    unset($data[$key]);
                }
            }

            $stmt = $db->prepare("UPDATE users SET data = ?, updated_at = NOW() WHERE user_id = ?");
            $success = $stmt->execute([json_encode($data), $userId]);

            return $success;
        } catch (\Throwable $e) {
            if (Config::isDebugMode()) {
                error_log("Error clearing state: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function clearAll(Nutgram $bot): bool
    {
        global $db;

        $userId = $bot->userId();
        if (!$userId) return false;

        try {
            $stmt = $db->prepare("UPDATE users SET data = NULL, updated_at = NOW() WHERE user_id = ?");
            $success = $stmt->execute([$userId]);

            return $success;
        } catch (\Throwable $e) {
            if (Config::isDebugMode()) {
                error_log("Error clearing all states: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function getState(Nutgram $bot): ?string
    {
        return self::get($bot, self::STATE);
    }

    public static function setState(Nutgram $bot, ?string $value): bool
    {
        return self::set($bot, self::STATE, $value);
    }

    public static function getScreen(Nutgram $bot): ?string
    {
        return self::get($bot, self::SCREEN);
    }

    public static function setScreen(Nutgram $bot, string $screen): bool
    {
        return self::set($bot, self::SCREEN, $screen);
    }

    public static function isInState(Nutgram $bot, string|array $states): bool
    {
        $currentState = self::getState($bot);
        if (is_array($states)) {
            return in_array($currentState, $states);
        }
        return $currentState === $states;
    }
}
