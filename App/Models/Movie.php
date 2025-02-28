<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Movie
{
    /**
     * Barcha kinolarni olish.
     * @param PDO $db Database ulanish.
     * @param int|null $limit Sahifadagi kinolar soni.
     * @param int $offset Boshlanish pozitsiyasi.
     * @return array Kinolar massivi.
     */
    public static function getAll(PDO $db, ?int $limit = null, int $offset = 0): array
    {
        try {
            $limit = $limit ?? Config::getItemsPerPage();

            $sql = "
                SELECT 
                    m.*,
                    (SELECT COUNT(*) FROM movie_videos WHERE movie_id = m.id) as video_count
                FROM 
                    movies m 
                ORDER BY 
                    m.created_at DESC 
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Kinoni ID bo'yicha topish.
     * @param PDO $db Database ulanish.
     * @param int $id Kino ID.
     * @param int|null $userId Foydalanuvchi ID (like statusini aniqlash uchun).
     * @return array|null Kino ma'lumotlari yoki null.
     */
    public static function findByID(PDO $db, int $id, ?int $userId = null): ?array
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

            return $movie;
        } catch (Exception $e) {
            throw new Exception("Kinoni olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Kinolarni qidirish.
     * @param PDO $db Database ulanish.
     * @param string $query Qidirish so'rovi.
     * @param int|null $userId Foydalanuvchi ID.
     * @param int $limit Sahifadagi natijalar soni.
     * @param int $offset Boshlanish pozitsiyasi.
     * @return array Topilgan kinolar massivi.
     */
    public static function searchByText(PDO $db, string $query, ?int $userId = null, int $limit = 10, int $offset = 0): array
    {
        try {
            $query = trim($query);

            $sql = "
                SELECT 
                    m.*,
                    COUNT(mv.id) as video_count,
                    CASE WHEN ul.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                FROM 
                    movies m 
                LEFT JOIN 
                    movie_videos mv ON m.id = mv.movie_id
                LEFT JOIN 
                    user_likes ul ON m.id = ul.movie_id AND ul.user_id = :user_id
                WHERE 
                    LOWER(m.title) LIKE LOWER(:query) 
                GROUP BY 
                    m.id
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

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function ($movie) {
                $movie['id'] = (int)$movie['id'];
                $movie['year'] = (int)$movie['year'];
                $movie['video_count'] = (int)$movie['video_count'];
                $movie['views'] = (int)$movie['views'];
                $movie['likes'] = (int)$movie['likes'];
                $movie['is_liked'] = (bool)$movie['is_liked'];
                return $movie;
            }, $results);
        } catch (Exception $e) {
            throw new Exception("Qidirishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Qidiruv natijalari sonini olish.
     * @param PDO $db Database ulanish.
     * @param string $query Qidirish so'rovi.
     * @return int Topilgan kinolar soni.
     */
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

    /**
     * Barcha kinolar sonini olish.
     * @param PDO $db Database ulanish.
     * @return int Kinolar soni.
     */
    public static function getCountMovies(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM movies");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Kinolar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Trend kinolarni olish (ko'rishlar va likelar bo'yicha).
     * @param PDO $db Database ulanish.
     * @param int $limit Sahifadagi kinolar soni.
     * @param int|null $userId Foydalanuvchi ID.
     * @param int $offset Boshlanish pozitsiyasi.
     * @return array Trend kinolar massivi.
     */
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
                ORDER BY 
                    m.views DESC, m.likes DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function ($movie) {
                $movie['id'] = (int)$movie['id'];
                $movie['year'] = (int)$movie['year'];
                $movie['video_count'] = (int)$movie['video_count'];
                $movie['views'] = (int)$movie['views'];
                $movie['likes'] = (int)$movie['likes'];
                $movie['is_liked'] = (bool)$movie['is_liked'];
                return $movie;
            }, $results);
        } catch (Exception $e) {
            throw new Exception("Trend kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Foydalanuvchi sevimli kinolarini olish.
     * @param PDO $db Database ulanish.
     * @param int $userId Foydalanuvchi ID.
     * @param int $limit Sahifadagi kinolar soni.
     * @param int $offset Boshlanish pozitsiyasi.
     * @return array Sevimli kinolar massivi.
     */
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

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Sevimli kinolarni olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Foydalanuvchi sevimli kinolari sonini olish.
     * @param PDO $db Database ulanish.
     * @param int $userId Foydalanuvchi ID.
     * @return int Sevimli kinolar soni.
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
            throw new Exception("Sevimli kinolar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Yangi kino qo'shish
     * @param PDO $db Database ulanish
     * @param array $data Kino ma'lumotlari
     * @return int Yangi kino ID
     */
    public static function create(PDO $db, array $data): int
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

    /**
     * Kino ma'lumotlarini yangilash.
     * @param PDO $db Database ulanish.
     * @param int $id Kino ID.
     * @param array $data Yangilanadigan ma'lumotlar.
     */
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

    /**
     * Kinoni o'chirish.
     * @param PDO $db Database ulanish.
     * @param int $id Kino ID.
     */
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

            $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kinoni o'chirishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Kino uchun like/unlike qilish.
     * @param PDO $db Database ulanish.
     * @param int $userId Foydalanuvchi ID.
     * @param int $movieId Kino ID.
     * @return bool Kinoga like qo'yildimi (true) yoki olib tashlandi (false).
     */
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

    /**
     * Kino ko'rishlarni qo'shish.
     * @param PDO $db Database ulanish.
     * @param int $userId Foydalanuvchi ID.
     * @param int $movieId Kino ID.
     */
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

                $stmt = $db->prepare("UPDATE movies SET views = views + 1 WHERE id = ?");
                $stmt->execute([$movieId]);

                $db->commit();
            } else {
                $db->rollBack();
            }
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Ko'rishni qo'shishda xatolik: " . $e->getMessage());
        }
    }
}