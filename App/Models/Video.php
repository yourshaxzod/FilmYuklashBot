<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Video
{
    public static function getAllByMovie(PDO $db, int $movieId): array
    {
        try {
            $sql = "SELECT v.*, m.title as movie_title
                    FROM movie_videos v
                    JOIN movies m ON v.movie_id = m.id
                    WHERE v.movie_id = :movie_id
                    ORDER BY v.id ASC";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Videolarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function find(PDO $db, int $id): ?array
    {
        try {
            $sql = "SELECT v.*, m.title as movie_title, m.id as movie_id
                    FROM movie_videos v
                    JOIN movies m ON v.movie_id = m.id
                    WHERE v.id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Videoni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findByPart(PDO $db, int $movieId, int $partNumber): ?array
    {
        try {
            $sql = "SELECT v.*, m.title as movie_title
                    FROM movie_videos v
                    JOIN movies m ON v.movie_id = m.id
                    WHERE v.movie_id = :movie_id AND v.id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $partNumber, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Videoni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getNextPartNumber(PDO $db, int $movieId): int
    {
        try {
            $sql = "SELECT MAX(id) as max_part 
                    FROM movie_videos 
                    WHERE movie_id = :movie_id";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['max_part'] ?? 0) + 1;
        } catch (Exception $e) {
            throw new Exception("Qism raqamini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function create(PDO $db, array $data): int
    {
        try {
            $db->beginTransaction();

            $movie = Movie::find($db, $data['movie_id']);
            if (!$movie) {
                throw new Exception("Kino topilmadi");
            }

            $sql = "INSERT INTO movie_videos (
                        movie_id, title, file_id, created_at
                    ) VALUES (
                        :movie_id, :title, :file_id, NOW()
                    )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'movie_id' => $data['movie_id'],
                'title' => $data['title'],
                'file_id' => $data['file_id']
            ]);

            $videoId = $db->lastInsertId();
            $db->commit();

            return $videoId;
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Video qo'shishda xatolik: " . $e->getMessage());
        }
    }

    public static function update(PDO $db, int $id, array $data): void
    {
        try {
            $db->beginTransaction();

            // Check if video exists
            $video = self::find($db, $id);
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

            $sql = "UPDATE movie_videos 
                    SET " . implode(', ', $fields) . ", updated_at = :updated_at 
                    WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute($values);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Videoni yangilashda xatolik: " . $e->getMessage());
        }
    }

    public static function delete(PDO $db, int $id): void
    {
        try {
            $db->beginTransaction();

            // Delete video
            $stmt = $db->prepare("DELETE FROM movie_videos WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // If no rows affected, video didn't exist
            if ($stmt->rowCount() === 0) {
                throw new Exception("Video topilmadi");
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Videoni o'chirishda xatolik: " . $e->getMessage());
        }
    }

    public static function reorderParts(PDO $db, int $movieId): void
    {
        try {
            $db->beginTransaction();

            // Get all videos ordered by part number
            $videos = self::getAllByMovie($db, $movieId);

            // Update part numbers to ensure sequential ordering
            foreach ($videos as $index => $video) {
                $newPartNumber = $index + 1;
                if ($video['id'] !== $newPartNumber) {
                    self::update($db, $video['id'], ['id' => $newPartNumber]);
                }
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Qismlarni tartibga solishda xatolik: " . $e->getMessage());
        }
    }

    public static function getStats(PDO $db, int $movieId): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_parts,
                        MIN(created_at) as first_added,
                        MAX(created_at) as last_added,
                        SUM(duration) as total_duration,
                        SUM(file_size) as total_size
                    FROM movie_videos
                    WHERE movie_id = :movie_id";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            throw new Exception("Statistikani olishda xatolik: " . $e->getMessage());
        }
    }
}
