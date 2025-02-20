<?php

namespace App\Models;

class Movie
{
    public static function findByCode(PDO $db, string $code): ?array
    {
        $stmt = $db->prepare("SELECT * FROM movies WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function searchByTitle(PDO $db, string $query): array
    {
        $stmt = $db->prepare("SELECT * FROM movies WHERE title LIKE ?");
        $stmt->execute(["%$query%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAll(PDO $db, int $limit, int $offset): array
    {
        $stmt = $db->prepare("SELECT * FROM movies ORDER BY title ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCount(PDO $db): int
    {
        $stmt = $db->query("SELECT COUNT(*) as count FROM movies");
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    public static function getTopMovies(PDO $db, int $limit = 5): array
    {
        $stmt = $db->prepare("SELECT * FROM movies ORDER BY views DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function incrementViews(PDO $db, int $id): void
    {
        $stmt = $db->prepare("UPDATE movies SET views = views + 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function create(PDO $db, array $data): int
    {
        $stmt = $db->prepare("INSERT INTO movies (title, code, description, photo_file_id, views) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([
            $data['title'],
            $data['code'],
            $data['description'],
            $data['photo_file_id']
        ]);
        return $db->lastInsertId();
    }

    public static function update(PDO $db, int $id, array $data): void
    {
        $fields = [];
        $values = [];
        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        $values[] = $id;

        $stmt = $db->prepare("UPDATE movies SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($values);
    }

    public static function delete(PDO $db, int $id): void
    {
        $db->beginTransaction();
        try {
            // Delete videos first
            $stmt = $db->prepare("DELETE FROM movie_videos WHERE movie_id = ?");
            $stmt->execute([$id]);

            // Delete movie
            $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
