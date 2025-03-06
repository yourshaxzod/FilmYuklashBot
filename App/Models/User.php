<?php

namespace App\Models;

use PDO;
use Exception;
use SergiX44\Nutgram\Nutgram;
use App\Helpers\Config;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class User
{
    public static function register(PDO $db, Nutgram $bot)
    {
        try {
            $userId = $bot->userId();
            $user = $bot->user();

            if (!$userId || !$user) {
                return null;
            }

            $existingUser = self::findById($db, $userId);

            if ($existingUser) {
                $data = [
                    'updated_at' => date("Y-m-d h:i:s"),
                ];

                self::update($db, $userId, $data);
                return self::findById($db, $userId);
            }

            $stmt = $db->prepare("
                INSERT INTO users (
                    user_id,
                    status
                ) VALUES (
                    :user_id,
                    :status
                )
            ");

            $stmt->execute([
                'user_id' => $userId,
                'status' => 'active'
            ]);

            return self::findById($db, $userId);
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchini ro'yxatdan o'tkazishda xatolik: " . $e->getMessage());
            }
            return null;
        }
    }

    public static function findById(PDO $db, int $userId)
    {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchini olishda xatolik: " . $e->getMessage());
            }
            return null;
        }
    }

    public static function update(PDO $db, int $userId, array $data)
    {
        try {
            $fields = [];
            $values = [];

            foreach ($data as $field => $value) {
                $fields[] = "{$field} = :{$field}";
                $values[$field] = $value;
            }

            $values['user_id'] = $userId;

            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = :user_id";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute($values);

            return $result;
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchini yangilashda xatolik: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function block(PDO $db, int $userId)
    {
        try {
            $stmt = $db->prepare("UPDATE users SET status = 'blocked', updated_at = NOW() WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchini bloklashda xatolik: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function unblock(PDO $db, int $userId)
    {
        try {
            $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchini blokdan chiqarishda xatolik: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function isBlocked(PDO $db, int $userId)
    {
        try {
            $stmt = $db->prepare("SELECT status FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $status = $stmt->fetchColumn();

            return $status === 'blocked';
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchi statusini tekshirishda xatolik: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function getAll(PDO $db, ?string $status = null, int $limit = 100, int $offset = 0)
    {
        try {
            $sql = "SELECT * FROM users";
            $params = [];

            if ($status !== null) {
                $sql .= " WHERE status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchilarni olishda xatolik: " . $e->getMessage());
            }
            return [];
        }
    }

    public static function getActiveUsersCount(PDO $db, int $days = 7)
    {
        try {
            $sql = "SELECT COUNT(DISTINCT user_id) FROM users WHERE updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$days]);

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Faol foydalanuvchilar sonini olishda xatolik: " . $e->getMessage());
            }
            return 0;
        }
    }

    public static function getNewUsersCount(PDO $db, int $days = 7)
    {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$days]);

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Yangi foydalanuvchilar sonini olishda xatolik: " . $e->getMessage());
            }
            return 0;
        }
    }

    public static function getUserStats(PDO $db, int $userId)
    {
        try {
            $stats = [
                'viewed_movies' => 0,
                'liked_movies' => 0,
                'categories' => [],
                'last_activity' => null,
                'joined_date' => null
            ];

            $user = self::findById($db, $userId);
            if (!$user) {
                return $stats;
            }

            $stats['joined_date'] = $user['created_at'];
            $stats['last_activity'] = $user['updated_at'];

            $sql = "SELECT COUNT(DISTINCT movie_id) FROM user_views WHERE user_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $stats['viewed_movies'] = (int)$stmt->fetchColumn();

            $sql = "SELECT COUNT(*) FROM user_likes WHERE user_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $stats['liked_movies'] = (int)$stmt->fetchColumn();

            $sql = "
                SELECT 
                    c.id, c.name, ui.score
                FROM 
                    user_interests ui
                JOIN 
                    categories c ON ui.category_id = c.id
                WHERE 
                    ui.user_id = ?
                ORDER BY 
                    ui.score DESC
                LIMIT 5
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $stats['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Foydalanuvchi statistikasini olishda xatolik: " . $e->getMessage());
            }
            return [
                'viewed_movies' => 0,
                'liked_movies' => 0,
                'categories' => [],
                'last_activity' => null,
                'joined_date' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    public static function broadcast(Nutgram $bot, PDO $db, string $message, ?array $options = null)
    {
        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        try {
            $status = $options['status'] ?? 'active';
            $limit = $options['limit'] ?? 1000;
            $offset = $options['offset'] ?? 0;

            $sql = "SELECT user_id FROM users WHERE status = ? LIMIT ? OFFSET ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$status, $limit, $offset]);
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $results['total'] = count($users);

            $parseMode = $options['parse_mode'] ?? ParseMode::HTML;
            $disableWebPagePreview = $options['disable_web_page_preview'] ?? false;
            $disableNotification = $options['disable_notification'] ?? false;

            $replyMarkup = null;
            if (isset($options['button_text']) && isset($options['button_url'])) {
                $replyMarkup = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make(
                        text: $options['button_text'],
                        url: $options['button_url']
                    ));
            }

            foreach ($users as $userId) {
                try {
                    $bot->sendMessage(
                        chat_id: $userId,
                        text: $message,
                        parse_mode: $parseMode,
                        disable_web_page_preview: $disableWebPagePreview,
                        disable_notification: $disableNotification,
                        reply_markup: $replyMarkup
                    );

                    $results['sent']++;

                    usleep(50000);
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "User {$userId}: " . $e->getMessage();

                    if (strpos($e->getMessage(), 'Forbidden: bot was blocked by the user') !== false) {
                        self::block($db, $userId);
                    }
                }
            }

            return $results;
        } catch (Exception $e) {
            if (Config::isDebugMode()) {
                error_log("Xabar yuborishda xatolik: " . $e->getMessage());
            }

            $results['errors'][] = "Global error: " . $e->getMessage();
            return $results;
        }
    }
}
