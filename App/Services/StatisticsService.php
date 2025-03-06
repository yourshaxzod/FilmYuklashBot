<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use App\Models\{User};
use App\Helpers\{Keyboard, Text, Validator};
use PDO;

class StatisticsService
{
    private const CACHE_KEY = 'bot_statistics';

    public static function showStats(Nutgram $bot, PDO $db)
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

    public static function refresh(Nutgram $bot, PDO $db)
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

    public static function collectStats(PDO $db, bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = CacheService::get(self::CACHE_KEY);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $db->beginTransaction();

            $stats = [
                'movies' => 0,
                'videos' => 0,
                'categories' => 0,
                'channels' => 0,
                'users' => 0,
                'views' => 0,
                'likes' => 0,
                'top_movie' => null,
                'top_category' => null,
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
                ],
                'active_users' => [
                    'today' => 0,
                    'week' => 0,
                    'month' => 0
                ]
            ];

            $sql = "SELECT 
                    (SELECT COUNT(*) FROM movies) as movies,
                    (SELECT COUNT(*) FROM movie_videos) as videos,
                    (SELECT COUNT(*) FROM categories) as categories,
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

            $sql = "SELECT 
                    c.*, 
                    COUNT(DISTINCT mc.movie_id) as movies_count,
                    SUM(m.views) as views
                    FROM categories c
                    JOIN movie_categories mc ON c.id = mc.category_id
                    JOIN movies m ON mc.movie_id = m.id
                    GROUP BY c.id
                    ORDER BY views DESC
                    LIMIT 1";

            $topCategory = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            if ($topCategory) {
                $stats['top_category'] = $topCategory;
            }

            // Bugungi statistika
            $todayStart = date('Y-m-d 00:00:00');
            $stats['today'] = self::getPeriodStats($db, $todayStart);

            // Haftalik statistika
            $weekStart = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $stats['week'] = self::getPeriodStats($db, $weekStart);

            // Oylik statistika
            $monthStart = date('Y-m-d 00:00:00', strtotime('-30 days'));
            $stats['month'] = self::getPeriodStats($db, $monthStart);

            // Faol foydalanuvchilar statistikasi
            $stats['active_users'] = [
                'today' => User::getActiveUsersCount($db, 1),
                'week' => User::getActiveUsersCount($db, 7),
                'month' => User::getActiveUsersCount($db, 30)
            ];

            // Eng faol soatlar
            $stats['peak_hours'] = self::getPeakHours($db);

            // Mashhur janrlar
            $stats['popular_categories'] = self::getPopularCategories($db);

            // Faollik trendlari
            $stats['trends'] = self::getActivityTrends($db);

            $db->commit();

            CacheService::set(self::CACHE_KEY, $stats);

            return $stats;
        } catch (\Exception $e) {
            $db->rollBack();
            throw new \Exception("Statistikani yig'ishda xatolik: " . $e->getMessage());
        }
    }

    private static function getPeriodStats(PDO $db, string $startDate)
    {
        try {
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
        } catch (\Exception $e) {
            error_log("Davr statistikasini olishda xatolik: " . $e->getMessage());
            return [
                'views' => 0,
                'likes' => 0,
                'new_users' => 0
            ];
        }
    }

    private static function getPeakHours(PDO $db)
    {
        try {
            $sql = "SELECT 
                HOUR(viewed_at) as hour,
                COUNT(*) as views
                FROM user_views
                WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY HOUR(viewed_at)
                ORDER BY views DESC
                LIMIT 5";

            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Eng faol soatlarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }

    private static function getPopularCategories(PDO $db)
    {
        try {
            $sql = "SELECT 
                c.id,
                c.name,
                COUNT(DISTINCT mc.movie_id) as movie_count,
                SUM(m.views) as total_views,
                SUM(m.likes) as total_likes
                FROM categories c
                JOIN movie_categories mc ON c.id = mc.category_id
                JOIN movies m ON mc.movie_id = m.id
                GROUP BY c.id, c.name
                ORDER BY total_views DESC
                LIMIT 5";

            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Mashhur kategoriyalarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }

    private static function getActivityTrends(PDO $db)
    {
        try {
            $sql = "SELECT 
                DATE(viewed_at) as date,
                COUNT(*) as views,
                COUNT(DISTINCT user_id) as unique_users
                FROM user_views
                WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(viewed_at)
                ORDER BY date DESC";

            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Faollik trendlarini olishda xatolik: " . $e->getMessage());
            return [];
        }
    }

    public static function clearCache()
    {
        CacheService::delete(self::CACHE_KEY);
    }
}
