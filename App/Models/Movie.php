<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Movie
{
    public static function getAll(PDO $db, ?int $limit = null, int $offset = 0, ?int $userId = null): array
    {
        try {
            $limit = $limit ?? Config::getItemsPerPage();

            $sql = "
                SELECT 
                    m.*,
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                    CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                FROM 
                    movies m 
                LEFT JOIN 
                    user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                ORDER BY 
                    m.created_at DESC 
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findById(PDO $db, int $id, ?int $userId = null): ?array
    {
        try {
            $sql = "
                SELECT 
                    m.*,
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                    CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                FROM 
                    movies m 
                LEFT JOIN 
                    user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                WHERE 
                    m.id = :id
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $movie = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$movie) {
                return null;
            }

            $movie['id'] = (int)$movie['id'];
            $movie['year'] = (int)$movie['year'];
            $movie['video_count'] = (int)$movie['video_count'];
            $movie['views'] = (int)$movie['views'];
            $movie['likes'] = (int)$movie['likes'];
            $movie['is_liked'] = (bool)$movie['is_liked'];

            $movie['categories'] = Category::getByMovieId($db, $movie['id']);

            return $movie;
        } catch (Exception $e) {
            throw new Exception("Kinoni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function searchByText(PDO $db, string $query, ?int $userId = null, int $limit = 10, int $offset = 0): array
    {
        try {
            $query = trim($query);

            $sql = "
            SELECT 
                m.*,
                (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                MAX(CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END) as is_liked
            FROM 
                movies m 
            LEFT JOIN 
                user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
            WHERE 
                LOWER(m.title) LIKE LOWER(:query) 
            GROUP BY 
                m.id, m.title, m.description, m.year, m.file_id, m.views, m.likes, m.created_at, m.updated_at
            ORDER BY 
                m.title ASC
            LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['id'] = (int)$movie['id'];
                $movies[$key]['year'] = (int)$movie['year'];
                $movies[$key]['video_count'] = (int)$movie['video_count'];
                $movies[$key]['views'] = (int)$movie['views'];
                $movies[$key]['likes'] = (int)$movie['likes'];
                $movies[$key]['is_liked'] = (bool)$movie['is_liked'];
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Qidirishda xatolik: " . $e->getMessage());
        }
    }

    public static function searchCount(PDO $db, string $query): int
    {
        try {
            $query = trim($query);

            $sql = "
                SELECT 
                    COUNT(DISTINCT m.id) as count
                FROM 
                    movies m 
                WHERE 
                    LOWER(m.title) LIKE LOWER(:query)
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Qidiruv natijalarini sanashda xatolik: " . $e->getMessage());
        }
    }

    public static function getByCategoryId(PDO $db, int $categoryId, ?int $userId = null, int $limit = 10, int $offset = 0): array
    {
        try {
            $sql = "
                SELECT 
                    m.*,
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                    CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                FROM 
                    movies m
                JOIN 
                    movie_categories mc ON m.id = mc.movie_id
                LEFT JOIN 
                    user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                WHERE 
                    mc.category_id = :category_id
                GROUP BY 
                    m.id
                ORDER BY 
                    m.created_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Kategoriya kinolarini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getCountByCategoryId(PDO $db, int $categoryId): int
    {
        try {
            $sql = "
                SELECT 
                    COUNT(DISTINCT m.id) as count
                FROM 
                    movies m
                JOIN 
                    movie_categories mc ON m.id = mc.movie_id
                WHERE 
                    mc.category_id = :category_id
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Kategoriya kinolarini sanashda xatolik: " . $e->getMessage());
        }
    }

    public static function getCountMovies(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM movies");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Kinolar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getTrendingMovies(PDO $db, int $limit = 10, ?int $userId = null, int $offset = 0): array
    {
        try {
            $sql = "
                SELECT 
                    m.*,
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                    CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                FROM 
                    movies m 
                LEFT JOIN 
                    user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                WHERE 
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) > 0
                ORDER BY 
                    (m.views * 0.7 + m.likes * 0.3) DESC, m.created_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Trend kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getRecommendations(PDO $db, int $userId, int $limit = 10, int $offset = 0): array
    {
        try {
            // Get viewed movies
            $sql = "SELECT DISTINCT movie_id FROM user_views WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $viewedMovieIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $threshold = Config::getRecommendationThreshold();

            $excludeIds = empty($viewedMovieIds) ? "0" : implode(",", $viewedMovieIds);

            // Main recommendations query
            $sql = "
            SELECT 
                m.*,
                (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                IF(ul.id IS NOT NULL, 1, 0) as is_liked,
                AVG(ui.score) as recommendation_score
            FROM 
                movies m
            JOIN 
                movie_categories mc ON m.id = mc.movie_id
            JOIN 
                user_interests ui ON mc.category_id = ui.category_id AND ui.user_id = :user_id
            LEFT JOIN 
                user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id2
            WHERE 
                m.id NOT IN ({$excludeIds})
                AND (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) > 0
            GROUP BY 
                m.id, m.title, m.description, m.year, m.file_id, m.views, m.likes, m.created_at, m.updated_at, ul.id
            HAVING 
                AVG(ui.score) >= :threshold
            ORDER BY 
                recommendation_score DESC, m.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id2', $userId, PDO::PARAM_INT); // Added second binding
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $recommendedMovies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($recommendedMovies as $key => $movie) {
                $recommendedMovies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            // Fill with trending if needed
            if (count($recommendedMovies) < $limit) {
                $moreNeeded = $limit - count($recommendedMovies);
                $existingIds = array_column($recommendedMovies, 'id');
                $excludeIds = array_merge($viewedMovieIds, $existingIds);
                $excludeIdsStr = empty($excludeIds) ? "0" : implode(",", $excludeIds);

                $sql = "
                SELECT 
                    m.*,
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                    IF(ul.id IS NOT NULL, 1, 0) as is_liked
                FROM 
                    movies m 
                LEFT JOIN 
                    user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                WHERE 
                    m.id NOT IN ({$excludeIdsStr})
                    AND (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) > 0
                GROUP BY
                    m.id, m.title, m.description, m.year, m.file_id, m.views, m.likes, m.created_at, m.updated_at, ul.id
                ORDER BY 
                    (m.views * 0.7 + m.likes * 0.3) DESC, m.created_at DESC
                LIMIT :limit
            ";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $moreNeeded, PDO::PARAM_INT);
                $stmt->execute();

                $trendingMovies = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($trendingMovies as $key => $movie) {
                    $trendingMovies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
                }

                $recommendedMovies = array_merge($recommendedMovies, $trendingMovies);
            }

            return $recommendedMovies;
        } catch (Exception $e) {
            throw new Exception("Tavsiyalarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getRecommendationsCount(PDO $db, int $userId): int
    {
        try {
            $sql = "SELECT DISTINCT movie_id FROM user_views WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $viewedMovieIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $threshold = Config::getRecommendationThreshold();

            $excludeIds = empty($viewedMovieIds) ? "0" : implode(",", $viewedMovieIds);

            $sql = "
            SELECT 
                COUNT(DISTINCT m.id) as count
            FROM 
                movies m
            JOIN 
                movie_categories mc ON m.id = mc.movie_id
            JOIN 
                user_interests ui ON mc.category_id = ui.category_id
            WHERE 
                ui.user_id = :user_id
                AND m.id NOT IN ({$excludeIds})
                AND (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) > 0
                AND (
                    SELECT AVG(score) 
                    FROM user_interests 
                    WHERE user_id = :user_id2
                    AND category_id IN (
                        SELECT category_id FROM movie_categories WHERE movie_id = m.id
                    )
                ) >= :threshold
        ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id2', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_STR);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Tavsiyalar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getLikedMovies(PDO $db, int $userId, int $limit = 10, int $offset = 0): array
    {
        try {
            $sql = "
                SELECT 
                    m.*, 
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                    1 as is_liked
                FROM 
                    movies m
                INNER JOIN 
                    user_likes ul ON m.id = ul.movie_id 
                WHERE 
                    ul.user_id = :user_id
                ORDER BY 
                    ul.liked_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Sevimli kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getLikedMoviesCount(PDO $db, int $userId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM user_likes WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Sevimli kinolar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function create(PDO $db, array $data, array $categoryIds = []): int
    {
        try {
            $db->beginTransaction();

            $sql = "
            INSERT INTO movies (
                title, 
                description, 
                year, 
                file_id, 
                created_at
            ) VALUES (
                :title, 
                :description, 
                :year, 
                :file_id, 
                NOW()
            )
        ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'title' => $data['title'],
                'description' => $data['description'],
                'year' => $data['year'],
                'file_id' => $data['file_id'] ?? null
            ]);

            $movieId = (int)$db->lastInsertId();

            if (!empty($categoryIds)) {
                $intCategoryIds = array_map('intval', $categoryIds);

                self::saveCategoriesInternal($db, $movieId, $intCategoryIds);
            }

            $db->commit();

            return $movieId;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Kino qo'shishda xatolik: " . $e->getMessage());
        }
    }

    private static function saveCategoriesInternal(PDO $db, int $movieId, array $categoryIds): void
    {
        try {
            $stmt = $db->prepare("DELETE FROM movie_categories WHERE movie_id = ?");
            $stmt->execute([$movieId]);

            if (!empty($categoryIds)) {
                $values = [];
                $placeholders = [];

                foreach ($categoryIds as $categoryId) {
                    $values[] = $movieId;
                    $values[] = $categoryId;
                    $placeholders[] = "(?, ?)";
                }

                $sql = "INSERT INTO movie_categories (movie_id, category_id) VALUES " . implode(", ", $placeholders);
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
            }
        } catch (Exception $e) {
            throw new Exception("Kino kategoriyalarini saqlashda xatolik: " . $e->getMessage());
        }
    }

    public static function update(PDO $db, int $id, array $data, ?array $categoryIds = null): void
    {
        try {
            $db->beginTransaction();

            $fields = [];
            $values = [];

            foreach ($data as $field => $value) {
                $fields[] = "$field = :$field";
                $values[$field] = $value;
            }

            $values['id'] = $id;
            $values['updated_at'] = date('Y-m-d H:i:s');

            $sql = "UPDATE movies SET " . implode(', ', $fields) . ", updated_at = :updated_at WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute($values);

            if ($categoryIds !== null) {
                Category::saveMovieCategories($db, $id, $categoryIds);
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kinoni yangilashda xatolik: " . $e->getMessage());
        }
    }

    public static function delete(PDO $db, int $id): void
    {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM movie_videos WHERE movie_id = ?");
            $stmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM user_likes WHERE movie_id = ?");
            $stmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM user_views WHERE movie_id = ?");
            $stmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM movie_categories WHERE movie_id = ?");
            $stmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kinoni o'chirishda xatolik: " . $e->getMessage());
        }
    }

    public static function toggleLike(PDO $db, int $userId, int $movieId): bool
    {
        try {
            // Tranzaksiyani boshlashdan oldin, tranzaksiya allaqachon boshlangan-yo'qligini tekshirish
            $needsTransaction = !$db->inTransaction();

            if ($needsTransaction) {
                $db->beginTransaction();
            }

            $stmt = $db->prepare("SELECT id FROM user_likes WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("DELETE FROM user_likes WHERE user_id = ? AND movie_id = ?");
                $stmt->execute([$userId, $movieId]);

                $stmt = $db->prepare("UPDATE movies SET likes = likes - 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                if ($needsTransaction) {
                    $db->commit();
                }
                return false;
            } else {
                $stmt = $db->prepare("INSERT INTO user_likes (user_id, movie_id, liked_at) VALUES (?, ?, NOW())");
                $stmt->execute([$userId, $movieId]);

                $stmt = $db->prepare("UPDATE movies SET likes = likes + 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                $movieCategories = Category::getByMovieId($db, $movieId);
                $increment = Config::getInterestIncrement();

                foreach ($movieCategories as $category) {
                    Category::updateUserInterest($db, $userId, $category['id'], $increment);
                }

                if ($needsTransaction) {
                    $db->commit();
                }
                return true;
            }
        } catch (Exception $e) {
            if ($needsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Yoqtirishda xatolik: " . $e->getMessage());
        }
    }

    public static function addView(PDO $db, int $userId, int $movieId): void
    {
        try {
            $needsTransaction = !$db->inTransaction();

            if ($needsTransaction) {
                $db->beginTransaction();
            }

            $sql = "
                SELECT id 
                FROM user_views 
                WHERE user_id = ? 
                  AND movie_id = ? 
                  AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId, $movieId]);
            $recentView = $stmt->fetch();

            if (!$recentView) {
                $stmt = $db->prepare("INSERT INTO user_views (user_id, movie_id, viewed_at) VALUES (?, ?, NOW())");
                $stmt->execute([$userId, $movieId]);

                $stmt = $db->prepare("UPDATE movies SET views = views + 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                $movieCategories = Category::getByMovieId($db, $movieId);
                $increment = Config::getInterestIncrement() * 0.5;

                foreach ($movieCategories as $category) {
                    Category::updateUserInterest($db, $userId, $category['id'], $increment);
                }
            }

            if ($needsTransaction) {
                $db->commit();
            }
        } catch (Exception $e) {
            if ($needsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Ko'rishni qo'shishda xatolik: " . $e->getMessage());
        }
    }
}
