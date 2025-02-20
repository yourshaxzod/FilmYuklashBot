<?php

namespace App\Models;

class Channel
{
    public static function getAll(PDO $db): array
    {
        $stmt = $db->prepare("SELECT * FROM channels ORDER BY title ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(PDO $db, string $username, string $title): void
    {
        $stmt = $db->prepare("INSERT INTO channels (username, title) VALUES (?, ?)");
        $stmt->execute([$username, $title]);
    }

    public static function delete(PDO $db, int $id): void
    {
        $stmt = $db->prepare("DELETE FROM channels WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function getCount(PDO $db): int
    {
        $stmt = $db->query("SELECT COUNT(*) as count FROM channels");
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
}
