<?php

namespace App\Models;

use PDO;
use Exception;

class Video
{
    public static function getAllByMovieId(PDO $db, int $movieId, int $limit = 10, int $offset = 0)
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

    public static function getCountByMovieId(PDO $db, int $movieId)
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

    public static function findById(PDO $db, int $id)
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

            return $video;
        } catch (Exception $e) {
            throw new Exception("Videoni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findByPart(PDO $db, int $movieId, int $partNumber)
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
                    AND v.part_number = :part_number
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->bindValue(':part_number', $partNumber, PDO::PARAM_INT);
            $stmt->execute();

            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return null;
            }

            return $video;
        } catch (Exception $e) {
            throw new Exception("Videoni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getNextPartNumber(PDO $db, int $movieId)
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

    public static function getNextVideo(PDO $db, int $movieId, int $currentPartNumber)
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
                    AND v.part_number > :current_part_number
                ORDER BY 
                    v.part_number ASC
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->bindValue(':current_part_number', $currentPartNumber, PDO::PARAM_INT);
            $stmt->execute();

            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return null;
            }

            return $video;
        } catch (Exception $e) {
            throw new Exception("Keyingi videoni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function create(PDO $db, array $data)
    {
        try {
            $db->beginTransaction();

            $movie = Movie::findById($db, $data['movie_id']);
            if (!$movie) {
                throw new Exception("Kino topilmadi");
            }

            $existing = self::findByPart($db, $data['movie_id'], $data['part_number']);
            if ($existing) {
                throw new Exception("Bu qism raqami allaqachon mavjud");
            }

            $sql = "
                INSERT INTO movie_videos (
                    movie_id, 
                    title, 
                    part_number, 
                    file_id, 
                    created_at
                ) VALUES (
                    :movie_id, 
                    :title, 
                    :part_number, 
                    :file_id,
                    NOW()
                )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'movie_id' => $data['movie_id'],
                'title' => $data['title'],
                'part_number' => $data['part_number'],
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

    public static function update(PDO $db, int $id, array $data)
    {
        try {
            $video = self::findById($db, $id);
            if (!$video) {
                throw new Exception("Video topilmadi");
            }

            if (isset($data['part_number']) && $data['part_number'] != $video['part_number']) {
                $existing = self::findByPart($db, $video['movie_id'], $data['part_number']);
                if ($existing) {
                    throw new Exception("Bu qism raqami allaqachon mavjud");
                }
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
        } catch (Exception $e) {
            throw new Exception("Videoni yangilashda xatolik: " . $e->getMessage());
        }
    }

    public static function delete(PDO $db, int $id)
    {
        try {
            $video = self::findById($db, $id);
            if (!$video) {
                throw new Exception("Video topilmadi");
            }

            $sql = "DELETE FROM movie_videos WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            throw new Exception("Videoni o'chirishda xatolik: " . $e->getMessage());
        }
    }
}
