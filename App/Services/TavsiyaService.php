<?php

namespace App\Services;

use PDO;
use Exception;
use App\Helpers\Config;
use App\Models\{Movie, Category, User};

/**
 * Tavsiya tizimi bilan ishlash uchun xizmat klassi
 */
class TavsiyaService
{
    /**
     * Tavsiya tizimini ishga tushirish
     * 
     * @param PDO $db Database ulanish
     * @return void
     */
    public static function init(PDO $db): void
    {
        try {
            // Tavsiya tizimi uchun zarur jadvallar mavjudligini tekshirish
            self::ensureTablesExist($db);
        } catch (Exception $e) {
            error_log("Tavsiya tizimini ishga tushirishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Tavsiya tizimi uchun kerakli jadvallar mavjudligini tekshirish
     * 
     * @param PDO $db Database ulanish
     * @return void
     */
    private static function ensureTablesExist(PDO $db): void
    {
        try {
            // user_interests jadvali mavjudligini tekshirish
            $sql = "
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'user_interests'
            ";
            $stmt = $db->query($sql);
            $exists = (bool)$stmt->fetchColumn();

            if (!$exists) {
                // user_interests jadvalini yaratish
                $sql = "
                    CREATE TABLE user_interests (
                        id BIGINT PRIMARY KEY AUTO_INCREMENT,
                        user_id BIGINT NOT NULL,
                        category_id INT NOT NULL,
                        score FLOAT DEFAULT 1.0,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_user_category (user_id, category_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                $db->exec($sql);

                // user_interests indeksi yaratish
                $sql = "CREATE INDEX idx_user_interests ON user_interests(user_id, score)";
                $db->exec($sql);

                if (Config::isDebugMode()) {
                    error_log("user_interests jadvali yaratildi");
                }
            }
        } catch (Exception $e) {
            error_log("Tavsiya tizimi jadvallarini tekshirishda xatolik: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Foydalanuvchi qiziqishlarini yangilash
     * (Kino ko'rilganda, yoqtirilganda, qism ko'rilganda)
     * 
     * @param PDO $db Database ulanish
     * @param int $userId Foydalanuvchi ID
     * @param int $movieId Kino ID
     * @param string $action Amal turi (view, like)
     * @return void
     */
    public static function updateUserInterests(PDO $db, int $userId, int $movieId, string $action = 'view'): void
    {
        try {
            // Kino kategoriyalarini olish
            $categories = Category::getByMovieId($db, $movieId);

            if (empty($categories)) {
                return; // Kategoriyalar yo'q bo'lsa, qaytish
            }

            // Amal turiga qarab qo'shiladigan bal
            $increment = match ($action) {
                'like' => Config::getInterestIncrement(),
                'view' => Config::getInterestIncrement() * 0.5,
                default => 0.0
            };

            if ($increment <= 0) {
                return; // Qo'shiladigan bal yo'q bo'lsa, qaytish
            }

            // Har bir kategoriya uchun foydalanuvchi qiziqishini yangilash
            foreach ($categories as $category) {
                Category::updateUserInterest($db, $userId, $category['id'], $increment);
            }
        } catch (Exception $e) {
            error_log("Foydalanuvchi qiziqishlarini yangilashda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Foydalanuvchi qiziqishlarini analiz qilish
     * 
     * @param PDO $db Database ulanish
     * @param int $userId Foydalanuvchi ID
     * @return array Analiz natijalari
     */
    public static function analyzeUserInterests(PDO $db, int $userId): array
    {
        try {
            // Foydalanuvchi eng ko'p qiziqadigan kategoriyalarni olish
            $topCategories = Category::getUserTopCategories($db, $userId, 5);

            // Foydalanuvchi ko'rgan kinolar soni
            $sql = "SELECT COUNT(DISTINCT movie_id) FROM user_views WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $viewedMoviesCount = (int)$stmt->fetchColumn();

            // Foydalanuvchi yoqtirgan kinolar soni
            $sql = "SELECT COUNT(*) FROM user_likes WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $likedMoviesCount = (int)$stmt->fetchColumn();

            // Tavsiya qilingan kinolar soni
            $recommendedMoviesCount = Movie::getRecommendationsCount($db, $userId);

            return [
                'top_categories' => $topCategories,
                'viewed_movies_count' => $viewedMoviesCount,
                'liked_movies_count' => $likedMoviesCount,
                'recommended_movies_count' => $recommendedMoviesCount,
                'interest_threshold' => Config::getRecommendationThreshold(),
                'has_recommendations' => $recommendedMoviesCount > 0
            ];
        } catch (Exception $e) {
            error_log("Foydalanuvchi qiziqishlarini analiz qilishda xatolik: " . $e->getMessage());
            return [
                'top_categories' => [],
                'viewed_movies_count' => 0,
                'liked_movies_count' => 0,
                'recommended_movies_count' => 0,
                'interest_threshold' => Config::getRecommendationThreshold(),
                'has_recommendations' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Kategoriya bo'yicha foydalanuvchi qiziqishini olish
     * 
     * @param PDO $db Database ulanish
     * @param int $userId Foydalanuvchi ID
     * @param int $categoryId Kategoriya ID
     * @return float Qiziqish bali
     */
    public static function getUserInterestInCategory(PDO $db, int $userId, int $categoryId): float
    {
        try {
            $sql = "SELECT score FROM user_interests WHERE user_id = :user_id AND category_id = :category_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();

            $score = $stmt->fetchColumn();

            return $score ? (float)$score : 0.0;
        } catch (Exception $e) {
            error_log("Foydalanuvchi qiziqishini olishda xatolik: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Qiziqishlar tizimini yuritish uchun muddat qo'yilgan vazifani bajarish
     * (Cron job orqali chaqiriladi)
     * 
     * @param PDO $db Database ulanish
     * @return array Natijalar
     */
    public static function runMaintenance(PDO $db): array
    {
        try {
            $results = [
                'removed_interests' => 0,
                'updated_users' => 0,
                'errors' => []
            ];

            // Eski qiziqishlarni tozalash (3 oydan eski)
            $sql = "DELETE FROM user_interests WHERE updated_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $results['removed_interests'] = $stmt->rowCount();

            // Aktiv bo'lmagan foydalanuvchilar (6 oydan ko'p kirmagan)
            $sql = "UPDATE users SET status = 'inactive' WHERE updated_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $results['updated_users'] = $stmt->rowCount();

            return $results;
        } catch (Exception $e) {
            error_log("Tavsiya tizimi muddatli vazifasini bajarishda xatolik: " . $e->getMessage());
            $results['errors'][] = $e->getMessage();
            return $results;
        }
    }

    /**
     * O'xshash kinolarni topish
     * 
     * @param PDO $db Database ulanish
     * @param int $movieId Kino ID
     * @param int $limit Natijalar soni
     * @return array O'xshash kinolar
     */
    public static function getSimilarMovies(PDO $db, int $movieId, int $limit = 5): array
    {
        try {
            // Kino kategoriyalarini olish
            $categories = Category::getByMovieId($db, $movieId);

            if (empty($categories)) {
                return []; // Kategoriyalar yo'q bo'lsa, bo'sh massiv qaytarish
            }

            $categoryIds = array_column($categories, 'id');

            // Umumiy kategoriyalar soni
            $totalCategories = count($categoryIds);

            // O'xshash kinolarni topish (umumiy kategoriyalar soniga qarab)
            $placeholders = implode(',', array_fill(0, $totalCategories, '?'));

            $sql = "
                SELECT 
                    m.*,
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                    COUNT(mc.category_id) as shared_categories,
                    COUNT(mc.category_id) / {$totalCategories} as similarity_score
                FROM 
                    movies m
                JOIN 
                    movie_categories mc ON m.id = mc.movie_id
                WHERE 
                    m.id != ? AND
                    mc.category_id IN ({$placeholders}) AND
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) > 0
                GROUP BY 
                    m.id
                HAVING 
                    shared_categories > 0
                ORDER BY 
                    similarity_score DESC, m.views DESC
                LIMIT ?
            ";

            $params = array_merge([$movieId], $categoryIds, [$limit]);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Kategoriyalarni qo'shib chiqish
            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (Exception $e) {
            error_log("O'xshash kinolarni olishda xatolik: " . $e->getMessage());
            return [];
        }
    }
}
