<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Video
{
    /**
     * Kino uchun barcha videolarni olish.
     * @param PDO $db Database ulanish.
     * @param int $movieId Kino ID.
     * @param int $limit Sahifadagi videolar soni.
     * @param int $offset Boshlanish pozitsiyasi.
     * @return array Videolar massivi.
     */
    public static function getAllByMovieID(PDO $db, int $movieId, int $limit = 10, int $offset = 0): array
    {
        try {
            $sql = "
                SELECT 
                    v.*, 
                    m.title as movie_title
                FROM 
                    movie_videos v
                JOIN 
                    movies m ON v.movie_id = m.id
                WHERE 
                    v.movie_id = :movie_id
                ORDER BY 
                    v.part_number ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Videolarni olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Kino uchun videolar sonini olish.
     * @param PDO $db Database ulanish.
     * @param int $movieId Kino ID.
     * @return int Videolar soni.
     */
    public static function getCountByMovieID(PDO $db, int $movieId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM movie_videos WHERE movie_id = :movie_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Video sonini olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Videoni ID bo'yicha topish.
     * @param PDO $db Database ulanish.
     * @param int $id Video ID.
     * @return array|null Video ma'lumotlari yoki null.
     */
    public static function findByID(PDO $db, int $id): ?array
    {
        try {
            $sql = "
                SELECT 
                    v.*, 
                    m.title as movie_title, 
                    m.id as movie_id
                FROM 
                    movie_videos v
                JOIN 
                    movies m ON v.movie_id = m.id
                WHERE 
                    v.id = :id
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return null;
            }

            $video['id'] = (int)$video['id'];
            $video['movie_id'] = (int)$video['movie_id'];
            if (isset($video['duration'])) {
                $video['duration'] = (int)$video['duration'];
            }
            if (isset($video['file_size'])) {
                $video['file_size'] = (int)$video['file_size'];
            }

            return $video;
        } catch (Exception $e) {
            throw new Exception("Videoni olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Kino uchun keyingi qism raqamini olish.
     * @param PDO $db Database ulanish.
     * @param int $movieId Kino ID.
     * @return int Keyingi qism raqami.
     */
    public static function getNextPartNumber(PDO $db, int $movieId): int
    {
        try {
            $sql = "
                SELECT 
                    MAX(part_number) as max_part 
                FROM 
                    movie_videos 
                WHERE 
                    movie_id = :movie_id
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['max_part'] ?? 0) + 1;
        } catch (Exception $e) {
            throw new Exception("Qism raqamini olishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Yangi video qo'shish.
     * @param PDO $db Database ulanish.
     * @param array $data Video ma'lumotlari.
     * @return int Yangi video ID.
     */
    public static function create(PDO $db, array $data): int
    {
        try {
            $db->beginTransaction();

            $movie = Movie::findByID($db, $data['movie_id']);
            if (!$movie) {
                throw new Exception("Kino topilmadi");
            }

            $sql = "
                INSERT INTO movie_videos (
                    movie_id, 
                    description, 
                    file_id, 
                    duration,
                    file_size,
                    created_at
                ) VALUES (
                    :movie_id, 
                    :description, 
                    :file_id,
                    NOW()
                )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'movie_id' => $data['movie_id'],
                'description' => $data['description'],
                'file_id' => $data['file_id'],
            ]);

            $videoId = $db->lastInsertId();
            $db->commit();

            return $videoId;
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Video qo'shishda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Video ma'lumotlarini yangilash.
     * @param PDO $db Database ulanish.
     * @param int $id Video ID.
     * @param array $data Yangilanadigan ma'lumotlar.
     */
    public static function update(PDO $db, int $id, array $data): void
    {
        try {
            $db->beginTransaction();

            $video = self::findByID($db, $id);
            if (!$video) {
                throw new Exception("Video topilmadi");
            }

            $fields = [];
            $values = [];

            foreach ($data as $field => $value) {
                $fields[] = "$field = :$field";
                $values[$field] = $value;
            }

            $values['id'] = $id;
            $values['updated_at'] = date('Y-m-d H:i:s');

            $sql = "
                UPDATE movie_videos 
                SET " . implode(', ', $fields) . ", updated_at = :updated_at 
                WHERE id = :id
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute($values);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Videoni yangilashda xatolik: " . $e->getMessage());
        }
    }

    /**
     * Videoni o'chirish.
     * @param PDO $db Database ulanish.
     * @param int $id Video ID.
     */
    public static function delete(PDO $db, int $id): void
    {
        try {
            $db->beginTransaction();

            $video = self::findByID($db, $id);
            if (!$video) {
                throw new Exception("Video topilmadi");
            }

            $sql = "DELETE FROM movie_videos WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Videoni o'chirishda xatolik: " . $e->getMessage());
        }
    }
}
