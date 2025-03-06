<?php

namespace App\Models;

use PDO;
use Exception;
use SergiX44\Nutgram\Nutgram;

class Channel
{
    /**
     * Get all channels
     */
    public static function getAll(PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT * FROM channels ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error getting channels: " . $e->getMessage());
        }
    }

    /**
     * Get active channels
     */
    public static function getActive(PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT * FROM channels WHERE is_active = 1 ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error getting active channels: " . $e->getMessage());
        }
    }

    /**
     * Find channel by ID
     */
    public static function find(PDO $db, int $id): ?array
    {
        try {
            $stmt = $db->prepare("SELECT * FROM channels WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Error finding channel: " . $e->getMessage());
        }
    }

    /**
     * Find channel by username
     */
    public static function findByUsername(PDO $db, string $username): ?array
    {
        try {
            $username = ltrim($username, '@');

            $stmt = $db->prepare("SELECT * FROM channels WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Error finding channel: " . $e->getMessage());
        }
    }

    /**
     * Create a new channel
     */
    public static function create(PDO $db, array $data): int
    {
        try {
            $db->beginTransaction();

            if (isset($data['username'])) {
                $username = ltrim($data['username'], '@');
                $existing = self::findByUsername($db, $username);
                if ($existing) {
                    throw new Exception("A channel with this username already exists");
                }
                $data['username'] = $username;
            }

            $sql = "INSERT INTO channels (username, title, chat_id, is_active, created_at) 
                    VALUES (:username, :title, :chat_id, :is_active, NOW())";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'username' => $data['username'],
                'title' => $data['title'] ?? null,
                'chat_id' => $data['chat_id'],
                'is_active' => $data['is_active'] ?? true
            ]);

            $channelId = (int)$db->lastInsertId();
            $db->commit();

            return $channelId;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error adding channel: " . $e->getMessage());
        }
    }

    /**
     * Delete a channel
     */
    public static function delete(PDO $db, int $id): bool
    {
        try {
            $db->beginTransaction();

            $channel = self::find($db, $id);
            if (!$channel) {
                throw new Exception("Channel not found");
            }

            $stmt = $db->prepare("DELETE FROM channels WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error deleting channel: " . $e->getMessage());
        }
    }

    /**
     * Toggle channel active status
     */
    public static function toggleActive(PDO $db, int $id): bool
    {
        try {
            $db->beginTransaction();

            $channel = self::find($db, $id);
            if (!$channel) {
                throw new Exception("Channel not found");
            }

            $newStatus = !$channel['is_active'];

            $stmt = $db->prepare("UPDATE channels SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $db->commit();
            return $newStatus;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Error toggling channel status: " . $e->getMessage());
        }
    }

    /**
     * Check user subscription to all required channels
     */
    public static function checkUserSubscription(Nutgram $bot, PDO $db, int $userId): array
    {
        $channels = self::getActive($db);
        $notSubscribed = [];

        foreach ($channels as $channel) {
            try {
                $chatMember = $bot->getChatMember(
                    chat_id: $channel['chat_id'],
                    user_id: $userId
                );

                $status = $chatMember->status;

                if (!in_array($status, ['creator', 'administrator', 'member'])) {
                    $notSubscribed[] = $channel;
                }
            } catch (\Exception $e) {
                $notSubscribed[] = $channel;
            }
        }

        return $notSubscribed;
    }

    /**
     * Check if bot is admin in a channel
     */
    public static function checkBotIsAdmin(Nutgram $bot, string $username): ?array
    {
        $username = ltrim($username, '@');

        try {
            $chat = $bot->getChat('@' . $username);

            if ($chat->type !== 'channel') {
                return null;
            }

            $botInfo = $bot->getMe();

            $chatMember = $bot->getChatMember(
                chat_id: $chat->id,
                user_id: $botInfo->id
            );

            if (!in_array($chatMember->status, ['creator', 'administrator'])) {
                return null;
            }

            return [
                'username' => $username,
                'title' => $chat->title,
                'chat_id' => $chat->id
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}