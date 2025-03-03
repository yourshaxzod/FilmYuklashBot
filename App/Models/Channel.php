<?php

namespace App\Models;

use PDO;
use Exception;
use SergiX44\Nutgram\Nutgram;

class Channel
{
    public static function getAll(PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT * FROM channels ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Kanallarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getActive(PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT * FROM channels WHERE is_active = 1 ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Kanallarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function find(PDO $db, int $id): ?array
    {
        try {
            $stmt = $db->prepare("SELECT * FROM channels WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Kanalni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findByUsername(PDO $db, string $username): ?array
    {
        try {
            $username = ltrim($username, '@');

            $stmt = $db->prepare("SELECT * FROM channels WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Kanalni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findByChatId(PDO $db, int $chatId): ?array
    {
        try {
            $stmt = $db->prepare("SELECT * FROM channels WHERE chat_id = ?");
            $stmt->execute([$chatId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            throw new Exception("Kanalni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function create(PDO $db, array $data): int
    {
        try {
            $db->beginTransaction();

            if (isset($data['username'])) {
                $username = ltrim($data['username'], '@');
                $existing = self::findByUsername($db, $username);
                if ($existing) {
                    throw new Exception("Bu username bilan kanal allaqachon mavjud");
                }
                $data['username'] = $username;
            }

            if (isset($data['chat_id'])) {
                $existing = self::findByChatId($db, $data['chat_id']);
                if ($existing) {
                    throw new Exception("Bu chat ID bilan kanal allaqachon mavjud");
                }
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

            $channelId = $db->lastInsertId();
            $db->commit();

            return $channelId;
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kanal qo'shishda xatolik: " . $e->getMessage());
        }
    }

    public static function update(PDO $db, int $id, array $data): void
    {
        try {
            $db->beginTransaction();

            $channel = self::find($db, $id);
            if (!$channel) {
                throw new Exception("Kanal topilmadi");
            }

            if (isset($data['username'])) {
                $username = ltrim($data['username'], '@');
                $existing = self::findByUsername($db, $username);
                if ($existing && $existing['id'] != $id) {
                    throw new Exception("Bu username bilan boshqa kanal mavjud");
                }
                $data['username'] = $username;
            }

            if (isset($data['chat_id']) && $data['chat_id'] != $channel['chat_id']) {
                $existing = self::findByChatId($db, $data['chat_id']);
                if ($existing && $existing['id'] != $id) {
                    throw new Exception("Bu chat ID bilan boshqa kanal mavjud");
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

            $sql = "UPDATE channels SET " . implode(', ', $fields) . ", updated_at = :updated_at WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute($values);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kanalni yangilashda xatolik: " . $e->getMessage());
        }
    }

    public static function delete(PDO $db, int $id): void
    {
        try {
            $db->beginTransaction();

            $channel = self::find($db, $id);
            if (!$channel) {
                throw new Exception("Kanal topilmadi");
            }

            $stmt = $db->prepare("DELETE FROM channels WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kanalni o'chirishda xatolik: " . $e->getMessage());
        }
    }

    public static function toggleActive(PDO $db, int $id): bool
    {
        try {
            $db->beginTransaction();

            $channel = self::find($db, $id);
            if (!$channel) {
                throw new Exception("Kanal topilmadi");
            }

            $newStatus = !$channel['is_active'];

            $stmt = $db->prepare("UPDATE channels SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $db->commit();

            return $newStatus;
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kanal statusini o'zgartirishda xatolik: " . $e->getMessage());
        }
    }

    public static function getCount(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM channels");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Kanallar sonini olishda xatolik: " . $e->getMessage());
        }
    }

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
