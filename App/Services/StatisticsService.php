<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{Movie, Video, Channel};
use App\Helpers\{Formatter, Keyboard, Config, Text, Validator};
use PDO;

class StatisticsService
{
    private const CACHE_KEY = 'bot_statistics';

    public static function showStats(Nutgram $bot, PDO $db): void
    {
        if (!Validator::isAdmin($bot)) {
            return;
        }

        try {
            $stats = self::collectStats($db);

            $bot->sendMessage(
                text: Text::statisticsInfo($stats),
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::statisticsActions()
            );
        } catch (\Exception $e) {
            $bot->sendMessage(text: "⚠️ Statistikani olishda xatolik: " . $e->getMessage());
        }
    }

    public static function refresh(Nutgram $bot, PDO $db): void
    {
        if (!Validator::isAdmin($bot)) {
            return;
        }

        try {
            $stats = self::collectStats($db, true);

            $bot->editMessageText(
                text: Text::statisticsInfo($stats),
                parse_mode: ParseMode::HTML,
                reply_markup: Keyboard::statisticsActions()
            );

            $bot->answerCallbackQuery(text: "✅ Statistika yangilandi");
        } catch (\Exception $e) {
            $bot->answerCallbackQuery(
                text: "⚠️ Xatolik: " . $e->getMessage(),
                show_alert: true
            );
        }
    }

    private static function collectStats(PDO $db, bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = self::getCachedStats();
            if ($cached) {
                return $cached;
            }
        }

        try {
            $db->beginTransaction();

            $stats = [
                'movies' => 0,
                'videos' => 0,
                'channels' => 0,
                'users' => 0,
                'views' => 0,
                'likes' => 0,
                'top_movie' => null,
                'today' => [
                    'views' => 0,
                    'likes' => 0,
                    'new_users' => 0
                ],
                'week' => [
                    'views' => 0,
                    'likes' => 0,
                    'new_users' => 0
                ],
                'month' => [
                    'views' => 0,
                    'likes' => 0,
                    'new_users' => 0
                ]
            ];

            $sql = "SELECT 
                    (SELECT COUNT(*) FROM movies) as movies,
                    (SELECT COUNT(*) FROM movie_videos) as videos,
                    (SELECT COUNT(*) FROM channels) as channels,
                    (SELECT COUNT(*) FROM users) as users,
                    (SELECT COUNT(*) FROM user_views) as views,
                    (SELECT COUNT(*) FROM user_likes) as likes";

            $basicStats = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $stats = array_merge($stats, $basicStats);

            $sql = "SELECT m.*, 
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count
                    FROM movies m 
                    ORDER BY m.views DESC, m.likes DESC 
                    LIMIT 1";

            $topMovie = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            if ($topMovie) {
                $stats['top_movie'] = $topMovie;
            }

            $todayStart = date('Y-m-d 00:00:00');
            $stats['today'] = self::getPeriodStats($db, $todayStart);

            $weekStart = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $stats['week'] = self::getPeriodStats($db, $weekStart);

            $monthStart = date('Y-m-d 00:00:00', strtotime('-30 days'));
            $stats['month'] = self::getPeriodStats($db, $monthStart);

            $stats['peak_hours'] = self::getPeakHours($db);

            if (self::hasGenreColumn($db)) {
                $stats['popular_genres'] = self::getPopularGenres($db);
            }

            $stats['trends'] = self::getActivityTrends($db);

            $db->commit();

            self::cacheStats($stats);

            return $stats;
        } catch (\Exception $e) {
            $db->rollBack();
            throw new \Exception("Statistikani yig'ishda xatolik: " . $e->getMessage());
        }
    }

    private static function getPeriodStats(PDO $db, string $startDate): array
    {
        $sql = "SELECT 
            (SELECT COUNT(*) FROM user_views WHERE viewed_at >= :start_date_views) as views,
            (SELECT COUNT(*) FROM user_likes WHERE liked_at >= :start_date_likes) as likes,
            (SELECT COUNT(*) FROM users WHERE created_at >= :start_date_users) as new_users";

        $stmt = $db->prepare($sql);

        $stmt->bindValue(':start_date_views', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':start_date_likes', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':start_date_users', $startDate, PDO::PARAM_STR);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function getPeakHours(PDO $db): array
    {
        $sql = "SELECT 
                HOUR(viewed_at) as hour,
                COUNT(*) as views
                FROM user_views
                WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY HOUR(viewed_at)
                ORDER BY views DESC
                LIMIT 5";

        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getPopularGenres(PDO $db): array
    {
        $sql = "SELECT 
                genre,
                COUNT(*) as movie_count,
                SUM(views) as total_views,
                SUM(likes) as total_likes
                FROM movies
                WHERE genre IS NOT NULL
                GROUP BY genre
                ORDER BY total_views DESC
                LIMIT 5";

        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getActivityTrends(PDO $db): array
    {
        $sql = "SELECT 
                DATE(viewed_at) as date,
                COUNT(*) as views,
                COUNT(DISTINCT user_id) as unique_users
                FROM user_views
                WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(viewed_at)
                ORDER BY date DESC";

        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function hasGenreColumn(PDO $db): bool
    {
        try {
            $sql = "SHOW COLUMNS FROM movies LIKE 'genre'";
            $result = $db->query($sql)->fetch();
            return (bool)$result;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function getCachedStats(): ?array
    {
        $cacheFile = self::getCacheFile();

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheTime = filemtime($cacheFile);
        if (time() - $cacheTime > Config::get('CACHE_TTL', 3600)) {
            return null;
        }

        $cached = file_get_contents($cacheFile);
        return $cached ? json_decode($cached, true) : null;
    }

    private static function cacheStats(array $stats): void
    {
        $cacheFile = self::getCacheFile();

        try {
            $cacheDir = dirname($cacheFile);
            if (!file_exists($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }

            file_put_contents($cacheFile, json_encode($stats));
        } catch (\Exception $e) {
        }
    }

    private static function getCacheFile(): string
    {
        return __DIR__ . '/../../cache/' . self::CACHE_KEY . '.json';
    }

    public static function clearCache(): void
    {
        $cacheFile = self::getCacheFile();
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
