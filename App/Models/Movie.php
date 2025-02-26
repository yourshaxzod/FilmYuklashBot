<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Movie
{
    public static function getAll(PDO $db, ?int $limit = null, int $offset = 0): array
    {
        try {
            $limit = $limit ?? Config::getItemsPerPage();

            $sql = "SELECT m.*,
                   (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count
                   FROM movies m 
                   ORDER BY m.created_at DESC 
                   LIMIT :limit OFFSET :offset";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function find(PDO $db, int $id, ?int $userId = null): ?array
    {
        try {
            $sql = "SELECT m.*,
                   (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                   CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                   FROM movies m 
                   LEFT JOIN user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                   WHERE m.id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Kinoni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findByCode(PDO $db, int $id, ?int $userId = null): ?array
    {
        try {
            $sql = "
            SELECT 
                m.*,
                CASE 
                    WHEN ul.id IS NOT NULL THEN 1 
                    ELSE 0 
                END as is_liked
            FROM movies m
            LEFT JOIN user_likes ul ON m.id = ul.movie_id 
                AND ul.user_id = :user_id
            WHERE m.id = :id
        ";

            $stmt = $db->prepare($sql);

            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId ?: 0, PDO::PARAM_INT);

            $stmt->execute();

            $movie = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$movie) {
                return null;
            }

            $movie['id'] = (int)$movie['id'];
            $movie['views'] = (int)$movie['views'];
            $movie['likes'] = (int)$movie['likes'];
            $movie['is_liked'] = (bool)$movie['is_liked'];

            return $movie;
        } catch (Exception $e) {
            throw new Exception("Kinoni topishda xatolik: " . $e->getMessage());
        }
    }

    public static function search(PDO $db, string $query, ?int $userId = null, int $limit = 50): array
    {
        try {
            // Validate and sanitize query
            $query = trim($query);
            if (empty($query)) {
                throw new Exception("Qidiruv so'rovi bo'sh bo'lishi mumkin emas");
            }

            // Build base query
            $sql = "SELECT m.*,
                COUNT(mv.id) as video_count,
                CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                FROM movies m 
                LEFT JOIN movie_videos mv ON m.id = mv.movie_id
                LEFT JOIN user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                WHERE LOWER(m.title) LIKE LOWER(:query) 
                GROUP BY m.id, m.title, m.description, m.file_id,
                         m.views, m.likes, m.created_at, ul.id
                ORDER BY m.title ASC
                LIMIT :limit";

            // Prepare and execute
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Transform results
            return array_map(function ($movie) {
                $movie['video_count'] = (int)$movie['video_count'];
                $movie['views'] = (int)$movie['views'];
                $movie['likes'] = (int)$movie['likes'];
                $movie['is_liked'] = (bool)$movie['is_liked'];
                return $movie;
            }, $results);
        } catch (Exception $e) {
            throw new Exception("Ma'lumotlar bazasida xatolik: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Qidirishda xatolik: " . $e->getMessage());
        }
    }

    public static function getCount(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM movies");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Kinolar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getTrending(PDO $db, int $limit = 10, ?int $userId = null): array
    {
        try {
            $sql = "SELECT m.*,
                   (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count,
                   CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                   FROM movies m 
                   LEFT JOIN user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                   ORDER BY m.views DESC, m.likes DESC
                   LIMIT :limit";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Trend kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getLikedMovies(PDO $db, int $userId): array
    {
        try {
            $sql = "SELECT 
                    m.*,
                    g.name as genre_name,
                    (SELECT COUNT(*) FROM videos WHERE movie_id = m.id) as videos_count
                FROM movies m
                INNER JOIN movie_likes ml ON m.id = ml.movie_id 
                WHERE ml.user_id = ?
                ORDER BY ml.created_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movies as &$movie) {
                $sql = "SELECT id, title, video FROM videos WHERE movie_id = ? ORDER BY id ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute([$movie['id']]);
                $movie['videos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $movies;
        } catch (Exception $e) {
            throw new Exception("Sevimli kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function create(PDO $db, array $data): int
    {
        try {
            $db->beginTransaction();

            $sql = "INSERT INTO movies (title, description, year, file_id, created_at)
                    VALUES (:title, :description, :year, :file_id, NOW())";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'title' => $data['title'],
                'description' => $data['description'],
                'year' => $data['year'],
                'file_id' => $data['file_id']
            ]);

            $movieId = $db->lastInsertId();
            $db->commit();

            return $movieId;
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kino qo'shishda xatolik: " . $e->getMessage());
        }
    }

    public static function update(PDO $db, int $id, array $data): void
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

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kinoni yangilashda xatolik: " . $e->getMessage());
        }
    }

    // DELETE METHOD
    public static function delete(PDO $db, int $id): void
    {
        try {
            $db->beginTransaction();

            // Delete videos
            $stmt = $db->prepare("DELETE FROM movie_videos WHERE movie_id = ?");
            $stmt->execute([$id]);

            // Delete likes
            $stmt = $db->prepare("DELETE FROM user_likes WHERE movie_id = ?");
            $stmt->execute([$id]);

            // Delete views
            $stmt = $db->prepare("DELETE FROM user_views WHERE movie_id = ?");
            $stmt->execute([$id]);

            // Delete movie
            $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kinoni o'chirishda xatolik: " . $e->getMessage());
        }
    }

    // LIKE/VIEW METHODS
    public static function toggleLike(PDO $db, int $userId, int $movieId): bool
    {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id FROM user_likes WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("DELETE FROM user_likes WHERE user_id = ? AND movie_id = ?");
                $stmt->execute([$userId, $movieId]);

                $stmt = $db->prepare("UPDATE movies SET likes = likes - 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                $db->commit();
                return false;
            } else {
                $stmt = $db->prepare("INSERT INTO user_likes (user_id, movie_id, liked_at) VALUES (?, ?, NOW())");
                $stmt->execute([$userId, $movieId]);

                $stmt = $db->prepare("UPDATE movies SET likes = likes + 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                $db->commit();
                return true;
            }
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Yoqtirishda xatolik: " . $e->getMessage());
        }
    }

    public static function addView(PDO $db, int $userId, int $movieId): void
    {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id FROM user_views WHERE user_id = ? AND movie_id = ?");
            $stmt->execute([$userId, $movieId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                $stmt = $db->prepare("INSERT INTO user_views (user_id, movie_id, viewed_at) VALUES (?, ?, NOW())");
                $stmt->execute([$userId, $movieId]);
                $existing = $stmt->fetch();

                $stmt = $db->prepare("UPDATE movies SET views = views + 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                $db->commit();
            }
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Ko'rishni qo'shishda xatolik: " . $e->getMessage());
        }
    }
}
