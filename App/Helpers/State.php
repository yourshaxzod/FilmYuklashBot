<?php

namespace App\Helpers;

use SergiX44\Nutgram\Nutgram;

class State
{
    public static function get(Nutgram $bot, string $key = 'state'): mixed
    {
        $data = $bot->getUserData('data') ?? [];
        return $data[$key] ?? null;
    }

    public static function set(Nutgram $bot, string $key, $value): void
    {
        $data = $bot->getUserData('data') ?? [];
        $data[$key] = $value;
        $bot->setUserData('data', $data);
    }

    public static function clear(Nutgram $bot, ?array $keys = null): void
    {
        if ($keys === null) {
            $data = $bot->getUserData('data') ?? [];
            unset($data['state']);
            $bot->setUserData('data', $data);
            return;
        }

        $data = $bot->getUserData('data') ?? [];
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        $bot->setUserData('data', $data);
    }

    public static function clearAll(Nutgram $bot): void
    {
        $bot->setUserData('data', []);
    }

    public static function getState(Nutgram $bot): ?string
    {
        return self::get($bot, 'state');
    }

    public static function setState(Nutgram $bot, $value): void
    {
        self::set($bot, 'state', $value);
    }

    public static function getUserData(Nutgram $bot): array
    {
        return $bot->getUserData('data') ?? [];
    }

    public static function startProcess(Nutgram $bot, string $process, array $data = []): void
    {
        self::set($bot, 'process', $process);
        self::set($bot, 'process_data', $data);
        self::set($bot, 'process_step', 0);
    }

    public static function getProcessData(Nutgram $bot): array
    {
        return self::get($bot, 'process_data') ?? [];
    }

    public static function updateProcessData(Nutgram $bot, array $data): void
    {
        $currentData = self::getProcessData($bot);
        self::set($bot, 'process_data', array_merge($currentData, $data));
    }

    public static function getProcessStep(Nutgram $bot): int
    {
        return self::get($bot, 'process_step') ?? 0;
    }

    public static function setProcessStep(Nutgram $bot, int $step): void
    {
        self::set($bot, 'process_step', $step);
    }

    public static function nextProcessStep(Nutgram $bot): int
    {
        $step = self::getProcessStep($bot) + 1;
        self::setProcessStep($bot, $step);
        return $step;
    }

    public static function endProcess(Nutgram $bot): void
    {
        self::clear($bot, ['process', 'process_data', 'process_step']);
    }
}