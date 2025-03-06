<?php

namespace App\Models;

use PDO;
use Exception;

class Video
{
    /**
     * Get all videos for a movie
     */
    public static function getAllByMovieId(PDO $db, int $movieId): array
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
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error getting videos: " . $e->getMessage());
        }
    }

    /**
     * Get count of videos for a movie
     */
    public static function getCountByMovieId(PDO $db, int $movieId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM movie_videos WHERE movie_id = :movie_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Error getting video count: " . $e->getMessage());
        }
    }

    /**
     * Find a video by ID
     */
    public static function findById(PDO $db, int $id): ?array
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
            throw new Exception("Error finding video: " . $e->getMessage());
        }
    }

    /**
     * Find a video by part number
     */
    public static function findByPart(PDO $db, int $movieId, int $partNumber): ?array
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
            throw new Exception("Error finding video: " . $e->getMessage());
        }
    }

    /**
     * Get next part number for a movie
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
            throw new Exception("Error getting next part number: " . $e->getMessage());
        }
    }

    /**
     * Create a new video
     */
    public static function create(PDO $db, array $data): int
    {
        try {
            $db->beginTransaction();

            // Check if movie exists
            $stmt = $db->prepare("SELECT id FROM movies WHERE id = ?");
            $stmt->execute([$data['movie_id']]);
            if (!$stmt->fetch()) {
                throw new Exception("Movie not found");
            }

            // Check if part number already exists
            if (isset($data['part_number'])) {
                $stmt = $db->prepare("SELECT id FROM movie_videos WHERE movie_id = ? AND part_number = ?");
                $stmt->execute([$data['movie_id'], $data['part_number']]);
                if ($stmt->fetch()) {
                    throw new Exception("This part number already exists for this movie");
                }
            }

            // If no part number provided, use next part number
            if (!isset($data['part_number'])) {
                $data['part_number'] = self::getNextPartNumber($db, $data['movie_id']);
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

            $videoId = (int)$db->lastInsertId();
            $db->commit();

            return $videoId;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error adding video: " . $e->getMessage());
        }
    }

    /**
     * Update a video
     */
    public static function update(PDO $db, int $id, array $data): bool
    {
        try {
            $db->beginTransaction();

            $video = self::findById($db, $id);
            if (!$video) {
                throw new Exception("Video not found");
            }

            // Check if part number already exists
            if (isset($data['part_number']) && $data['part_number'] != $video['part_number']) {
                $stmt = $db->prepare("SELECT id FROM movie_videos WHERE movie_id = ? AND part_number = ?");
                $stmt->execute([$video['movie_id'], $data['part_number']]);
                $existingVideo = $stmt->fetch();
                if ($existingVideo && $existingVideo['id'] != $id) {
                    throw new Exception("This part number already exists for this movie");
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

            $sql = "UPDATE movie_videos SET " . implode(', ', $fields) . ", updated_at = :updated_at WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute($values);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error updating video: " . $e->getMessage());
        }
    }

    /**
     * Delete a video
     */
    public static function delete(PDO $db, int $id): bool
    {
        try {
            $db->beginTransaction();

            $video = self::findById($db, $id);
            if (!$video) {
                throw new Exception("Video not found");
            }

            $stmt = $db->prepare("DELETE FROM movie_videos WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error deleting video: " . $e->getMessage());
        }
    }
}