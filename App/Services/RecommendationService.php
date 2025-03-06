<?php

namespace App\Services;

use PDO;
use Exception;
use App\Helpers\Config;
use App\Models\{Movie, Category};

class RecommendationService
{
    public static function init(PDO $db)
    {
        try {
            self::ensureTablesExist($db);
        } catch (Exception $e) {
            error_log("Tavsiya tizimini ishga tushirishda xatolik: " . $e->getMessage());
        }
    }

    private static function ensureTablesExist(PDO $db)
    {
        try {
            $sql = "
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'user_interests'
            ";
            $stmt = $db->query($sql);
            $exists = (bool)$stmt->fetchColumn();

            if (!$exists) {
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

    public static function updateUserInterests(PDO $db, int $userId, int $movieId, string $action = 'view')
    {
        try {
            $categories = Category::getByMovieId($db, $movieId);

            if (empty($categories)) {
                return;
            }

            $increment = match ($action) {
                'like' => Config::getInterestIncrement(),
                'view' => Config::getInterestIncrement() * 0.5,
                default => 0.0
            };

            if ($increment <= 0) {
                return;
            }

            foreach ($categories as $category) {
                Category::updateUserInterest($db, $userId, $category['id'], $increment);
            }
        } catch (Exception $e) {
            error_log("Foydalanuvchi qiziqishlarini yangilashda xatolik: " . $e->getMessage());
        }
    }

    public static function analyzeUserInterests(PDO $db, int $userId)
    {
        try {
            $topCategories = Category::getUserTopCategories($db, $userId, 5);

            $sql = "SELECT COUNT(DISTINCT movie_id) FROM user_views WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $viewedMoviesCount = (int)$stmt->fetchColumn();

            $sql = "SELECT COUNT(*) FROM user_likes WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $likedMoviesCount = (int)$stmt->fetchColumn();

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

    public static function getUserInterestInCategory(PDO $db, int $userId, int $categoryId)
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

    public static function runMaintenance(PDO $db)
    {
        try {
            $results = [
                'removed_interests' => 0,
                'updated_users' => 0,
                'errors' => []
            ];

            $sql = "DELETE FROM user_interests WHERE updated_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $results['removed_interests'] = $stmt->rowCount();

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

    public static function getSimilarMovies(PDO $db, int $movieId, int $limit = 5)
    {
        try {
            $categories = Category::getByMovieId($db, $movieId);

            if (empty($categories)) {
                return [];
            }

            $categoryIds = array_column($categories, 'id');

            $totalCategories = count($categoryIds);

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
