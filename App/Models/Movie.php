<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Movie
{
    /**
     * Get all movies
     */
    public static function getAll(PDO $db, ?int $limit = null, int $offset = 0, ?int $userId = null): array
    {
        try {
            $limit = $limit ?? Config::get('ITEMS_PER_PAGE');

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
                $movies[$key]['is_liked'] = (bool)$movie['is_liked'];
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Error getting movies: " . $e->getMessage());
        }
    }

    /**
     * Find a movie by ID
     */
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
            throw new Exception("Error finding movie: " . $e->getMessage());
        }
    }

    /**
     * Search movies by text
     */
    public static function searchByText(PDO $db, string $query, ?int $userId = null): array
    {
        try {
            $query = trim($query);
            $limit = Config::getSearchResultsLimit();

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
                    LOWER(m.title) LIKE LOWER(:query) 
                ORDER BY 
                    m.title ASC
                LIMIT :limit
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', "%{$query}%", PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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
            throw new Exception("Error searching: " . $e->getMessage());
        }
    }

    /**
     * Get movies by category ID
     */
    public static function getByCategoryId(PDO $db, int $categoryId, ?int $userId = null, int $limit = 10): array
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
                LIMIT :limit
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
                $movies[$key]['is_liked'] = (bool)$movie['is_liked'];
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Error getting category movies: " . $e->getMessage());
        }
    }

    /**
     * Get count of movies by category ID
     */
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
            throw new Exception("Error counting category movies: " . $e->getMessage());
        }
    }

    /**
     * Get total count of movies
     */
    public static function getCountMovies(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM movies");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Error getting movies count: " . $e->getMessage());
        }
    }

    /**
     * Get trending movies
     */
    public static function getTrendingMovies(PDO $db, int $limit = 10, ?int $userId = null): array
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
                LIMIT :limit
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
                $movies[$key]['is_liked'] = (bool)$movie['is_liked'];
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Error getting trending movies: " . $e->getMessage());
        }
    }

    /**
     * Get personalized recommendations
     */
    public static function getRecommendations(PDO $db, int $userId, int $limit = 5): array
    {
        try {
            // Get movies the user has already viewed
            $sql = "SELECT DISTINCT movie_id FROM user_views WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $viewedMovieIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // If no movies viewed yet, return empty array
            if (empty($viewedMovieIds)) {
                return self::getTrendingMovies($db, $limit, $userId);
            }

            $threshold = Config::getRecommendationThreshold();

            // Exclude already viewed movies
            $excludeIds = implode(",", array_map('intval', $viewedMovieIds));

            // If no viewed movies yet, just use 0 as a placeholder for exclusion
            if (empty($excludeIds)) {
                $excludeIds = "0";
            }

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
                    m.id
                HAVING 
                    AVG(ui.score) >= :threshold
                ORDER BY 
                    recommendation_score DESC, m.created_at DESC
                LIMIT :limit
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id2', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If not enough recommendations, supplement with trending movies
            if (count($recommendations) < $limit) {
                $moreNeeded = $limit - count($recommendations);
                $existingIds = array_column($recommendations, 'id');
                $allExcludeIds = array_merge($viewedMovieIds, $existingIds);
                $excludeStr = empty($allExcludeIds) ? "0" : implode(",", array_map('intval', $allExcludeIds));

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
                        m.id NOT IN ({$excludeStr})
                        AND (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) > 0
                    ORDER BY 
                        (m.views * 0.7 + m.likes * 0.3) DESC, m.created_at DESC
                    LIMIT :limit
                ";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $moreNeeded, PDO::PARAM_INT);
                $stmt->execute();

                $trendingMovies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $recommendations = array_merge($recommendations, $trendingMovies);
            }

            // Enrich with categories
            foreach ($recommendations as $key => $movie) {
                $recommendations[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
                $recommendations[$key]['is_liked'] = (bool)$movie['is_liked'];
            }

            return $recommendations;
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Error getting recommendations: " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Get liked movies
     */
    public static function getLikedMovies(PDO $db, int $userId, int $limit = 10): array
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
                LIMIT :limit
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as $key => $movie) {
                $movies[$key]['categories'] = Category::getByMovieId($db, $movie['id']);
                $movies[$key]['is_liked'] = true;
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Error getting liked movies: " . $e->getMessage());
        }
    }

    /**
     * Get count of liked movies
     */
    public static function getLikedMoviesCount(PDO $db, int $userId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM user_likes WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Error getting liked movies count: " . $e->getMessage());
        }
    }

    /**
     * Create a new movie
     */
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
                Category::saveMovieCategories($db, $movieId, $intCategoryIds);
            }

            $db->commit();
            return $movieId;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error adding movie: " . $e->getMessage());
        }
    }

    /**
     * Update a movie
     */
    public static function update(PDO $db, int $id, array $data, ?array $categoryIds = null): bool
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
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error updating movie: " . $e->getMessage());
        }
    }

    /**
     * Delete a movie
     */
    public static function delete(PDO $db, int $id): bool
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
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error deleting movie: " . $e->getMessage());
        }
    }

    /**
     * Toggle like for a movie
     */
    public static function toggleLike(PDO $db, int $userId, int $movieId): bool
    {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id FROM user_likes WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Unlike
                $stmt = $db->prepare("DELETE FROM user_likes WHERE user_id = ? AND movie_id = ?");
                $stmt->execute([$userId, $movieId]);

                $stmt = $db->prepare("UPDATE movies SET likes = likes - 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                $db->commit();
                return false;
            } else {
                // Check if user has reached max likes
                $maxLikes = Config::getMaxLikesPerUser();
                $stmt = $db->prepare("SELECT COUNT(*) FROM user_likes WHERE user_id = ?");
                $stmt->execute([$userId]);
                $currentLikes = (int)$stmt->fetchColumn();

                if ($currentLikes >= $maxLikes) {
                    $db->rollBack();
                    throw new Exception("Maksimal yoqtirish chegarasiga ({$maxLikes}) yetdingiz.");
                }

                // Like
                $stmt = $db->prepare("INSERT INTO user_likes (user_id, movie_id, liked_at) VALUES (?, ?, NOW())");
                $stmt->execute([$userId, $movieId]);

                $stmt = $db->prepare("UPDATE movies SET likes = likes + 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                // Update user interests
                $movieCategories = Category::getByMovieId($db, $movieId);
                $increment = Config::getInterestIncrement();

                foreach ($movieCategories as $category) {
                    Category::updateUserInterest($db, $userId, $category['id'], $increment);
                }

                $db->commit();
                return true;
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Add a view to a movie
     */
    public static function addView(PDO $db, int $userId, int $movieId): void
    {
        try {
            $db->beginTransaction();

            // Check if the user has viewed this movie recently (within 24 hours)
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
                // Add a new view record
                $stmt = $db->prepare("INSERT INTO user_views (user_id, movie_id, viewed_at) VALUES (?, ?, NOW())");
                $stmt->execute([$userId, $movieId]);

                // Increment the movie's view count
                $stmt = $db->prepare("UPDATE movies SET views = views + 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                // Update user interests
                $movieCategories = Category::getByMovieId($db, $movieId);
                $increment = Config::getInterestIncrement() * 0.5; // Half the interest increment for views

                foreach ($movieCategories as $category) {
                    Category::updateUserInterest($db, $userId, $category['id'], $increment);
                }
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if (Config::isDebugMode()) {
                error_log("Error adding view: " . $e->getMessage());
            }
        }
    }
}
