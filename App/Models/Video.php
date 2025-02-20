<?php

namespace App\Models;
use PDO;

class Video {
    public static function getAllByMovie(PDO $db, int $movieId): array {
        $stmt = $db->prepare("SELECT * FROM movie_videos WHERE movie_id = ? ORDER BY part_number ASC");
        $stmt->execute([$movieId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCount(PDO $db, int $movieId): int {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM movie_videos WHERE movie_id = ?");
        $stmt->execute([$movieId]);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    public static function create(PDO $db, array $data): int {
        $stmt = $db->prepare("INSERT INTO movie_videos (movie_id, title, video_file_id, part_number) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['movie_id'],
            $data['title'],
            $data['video_file_id'],
            $data['part_number']
        ]);
        return $db->lastInsertId();
    }

    public static function delete(PDO $db, int $id): void {
        $stmt = $db->prepare("DELETE FROM movie_videos WHERE id = ?");
        $stmt->execute([$id]);
    }
}