<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Category
{
    public static function getAll(PDO $db, ?int $limit = null, int $offset = 0)
    {
        try {
            $limit = $limit ?? Config::getItemsPerPage();

            $sql = "
                SELECT 
                    c.*,
                    (SELECT COUNT(*) FROM movie_categories WHERE category_id = c.id) as movies_count
                FROM 
                    categories c 
                ORDER BY 
                    c.name ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Kategoriyalarni olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findById(PDO $db, int $id)
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    (SELECT COUNT(*) FROM movie_categories WHERE category_id = c.id) as movies_count
                FROM 
                    categories c 
                WHERE 
                    c.id = :id
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return null;
            }

            return $category;
        } catch (Exception $e) {
            throw new Exception("Kategoriyani olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findBySlug(PDO $db, string $slug)
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    (SELECT COUNT(*) FROM movie_categories WHERE category_id = c.id) as movies_count
                FROM 
                    categories c 
                WHERE 
                    c.slug = :slug
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();

            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return null;
            }

            return $category;
        } catch (Exception $e) {
            throw new Exception("Kategoriyani olishda xatolik: " . $e->getMessage());
        }
    }

    public static function findByName(PDO $db, string $name)
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    (SELECT COUNT(*) FROM movie_categories WHERE category_id = c.id) as movies_count
                FROM 
                    categories c 
                WHERE 
                    c.name = :name
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->execute();

            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return null;
            }

            return $category;
        } catch (Exception $e) {
            throw new Exception("Kategoriyani olishda xatolik: " . $e->getMessage());
        }
    }

    public static function create(PDO $db, array $data)
    {
        try {
            $db->beginTransaction();

            $existingCategory = self::findByName($db, $data['name']);
            if ($existingCategory) {
                throw new Exception("Bu nomdagi kategoriya allaqachon mavjud");
            }

            if (!isset($data['slug']) || empty($data['slug'])) {
                $data['slug'] = self::createSlug($data['name']);
            }

            $existingSlug = self::findBySlug($db, $data['slug']);
            if ($existingSlug) {
                $data['slug'] = $data['slug'] . '-' . time();
            }

            $sql = "
                INSERT INTO categories (
                    name, 
                    slug, 
                    description, 
                    created_at
                ) VALUES (
                    :name, 
                    :slug, 
                    :description, 
                    NOW()
                )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);

            $categoryId = $db->lastInsertId();
            $db->commit();

            return $categoryId;
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kategoriya qo'shishda xatolik: " . $e->getMessage());
        }
    }

    public static function update(PDO $db, int $id, array $data)
    {
        try {
            $db->beginTransaction();

            $category = self::findById($db, $id);
            if (!$category) {
                throw new Exception("Kategoriya topilmadi");
            }

            if (isset($data['name']) && $data['name'] !== $category['name']) {
                $existingCategory = self::findByName($db, $data['name']);
                if ($existingCategory && $existingCategory['id'] != $id) {
                    throw new Exception("Bu nomdagi kategoriya allaqachon mavjud");
                }
            }

            if (isset($data['slug']) && $data['slug'] !== $category['slug']) {
                $existingSlug = self::findBySlug($db, $data['slug']);
                if ($existingSlug && $existingSlug['id'] != $id) {
                    throw new Exception("Bu slugli kategoriya allaqachon mavjud");
                }
            }

            $fields = [];
            $values = [];

            foreach ($data as $field => $value) {
                if ($field === 'name' || $field === 'slug' || $field === 'description') {
                    $fields[] = "$field = :$field";
                    $values[$field] = $value;
                }
            }

            if (empty($fields)) {
                $db->rollBack();
                return;
            }

            $values['id'] = $id;
            $values['updated_at'] = date('Y-m-d H:i:s');

            $sql = "UPDATE categories SET " . implode(', ', $fields) . ", updated_at = :updated_at WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute($values);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kategoriyani yangilashda xatolik: " . $e->getMessage());
        }
    }

    public static function delete(PDO $db, int $id)
    {
        try {
            $db->beginTransaction();

            $category = self::findById($db, $id);
            if (!$category) {
                throw new Exception("Kategoriya topilmadi");
            }

            $stmt = $db->prepare("DELETE FROM movie_categories WHERE category_id = ?");
            $stmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM user_interests WHERE category_id = ?");
            $stmt->execute([$id]);

            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Kategoriyani o'chirishda xatolik: " . $e->getMessage());
        }
    }

    public static function getCount(PDO $db)
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM categories");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Kategoriyalar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getByMovieId(PDO $db, int $movieId)
    {
        try {
            $sql = "
                SELECT 
                    c.*
                FROM 
                    categories c
                JOIN 
                    movie_categories mc ON c.id = mc.category_id
                WHERE 
                    mc.movie_id = :movie_id
                ORDER BY 
                    c.name ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Kino kategoriyalarini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function saveMovieCategories(PDO $db, int $movieId, array $categoryIds)
    {
        try {
            $shouldCommit = !$db->inTransaction();

            if ($shouldCommit) {
                $db->beginTransaction();
            }

            $stmt = $db->prepare("DELETE FROM movie_categories WHERE movie_id = ?");
            $stmt->execute([$movieId]);

            if (!empty($categoryIds)) {
                $values = [];
                $placeholders = [];

                foreach ($categoryIds as $categoryId) {

                    $categoryId = (int)$categoryId;
                    $values[] = $movieId;
                    $values[] = $categoryId;
                    $placeholders[] = "(?, ?)";
                }

                $sql = "INSERT INTO movie_categories (movie_id, category_id) VALUES " . implode(", ", $placeholders);
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
            }

            if ($shouldCommit) {
                $db->commit();
            }
        } catch (Exception $e) {
            if ($shouldCommit && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Kino kategoriyalarini saqlashda xatolik: " . $e->getMessage());
        }
    }

    public static function getUserTopCategories(PDO $db, int $userId, int $limit = 5)
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    ui.score as interest_score
                FROM 
                    categories c
                JOIN 
                    user_interests ui ON c.id = ui.category_id
                WHERE 
                    ui.user_id = :user_id
                ORDER BY 
                    ui.score DESC
                LIMIT :limit
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Foydalanuvchi qiziqishlarini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function updateUserInterest(PDO $db, int $userId, int $categoryId, float $increment = 0.0)
    {
        try {
            $needsTransaction = !$db->inTransaction();

            if ($needsTransaction) {
                $db->beginTransaction();
            }

            $category = self::findById($db, $categoryId);
            if (!$category) {
                if ($needsTransaction) {
                    $db->rollBack();
                }
                return;
            }

            $sql = "SELECT id, score FROM user_interests WHERE user_id = :user_id AND category_id = :category_id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();

            $interest = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$interest) {
                if ($increment <= 0) {
                    if ($needsTransaction) {
                        $db->rollBack();
                    }
                    return;
                }

                $sql = "INSERT INTO user_interests (user_id, category_id, score, updated_at) VALUES (:user_id, :category_id, :score, NOW())";
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $stmt->bindValue(':score', $increment, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                $newScore = $interest['score'] + $increment;
                $maxScore = Config::getMaxInterestScore();

                if ($newScore <= 0) {
                    $sql = "DELETE FROM user_interests WHERE id = :id";
                    $stmt = $db->prepare($sql);
                    $stmt->bindValue(':id', $interest['id'], PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    $sql = "UPDATE user_interests SET score = :score, updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($sql);
                    $stmt->bindValue(':id', $interest['id'], PDO::PARAM_INT);
                    $stmt->bindValue(':score', min($newScore, $maxScore), PDO::PARAM_STR);
                    $stmt->execute();
                }
            }

            if ($needsTransaction) {
                $db->commit();
            }
        } catch (Exception $e) {
            if ($needsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Foydalanuvchi qiziqishini yangilashda xatolik: " . $e->getMessage());
        }
    }

    private static function createSlug(string $name)
    {
        static $transliteration = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'j',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ъ' => '',
            'ы' => 'i',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'ў' => 'o',
            'қ' => 'q',
            'ғ' => 'g',
            'ҳ' => 'h',

            'А' => 'a',
            'Б' => 'b',
            'В' => 'v',
            'Г' => 'g',
            'Д' => 'd',
            'Е' => 'e',
            'Ё' => 'yo',
            'Ж' => 'j',
            'З' => 'z',
            'И' => 'i',
            'Й' => 'y',
            'К' => 'k',
            'Л' => 'l',
            'М' => 'm',
            'Н' => 'n',
            'О' => 'o',
            'П' => 'p',
            'Р' => 'r',
            'С' => 's',
            'Т' => 't',
            'У' => 'u',
            'Ф' => 'f',
            'Х' => 'h',
            'Ц' => 'ts',
            'Ч' => 'ch',
            'Ш' => 'sh',
            'Щ' => 'sch',
            'Ъ' => '',
            'Ы' => 'i',
            'Ь' => '',
            'Э' => 'e',
            'Ю' => 'yu',
            'Я' => 'ya',
            'Ў' => 'o',
            'Қ' => 'q',
            'Ғ' => 'g',
            'Ҳ' => 'h'
        ];

        $slug = strtr($name, $transliteration);

        $slug = mb_strtolower($slug, 'UTF-8');

        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

        $slug = preg_replace('/[\s-]+/', '-', $slug);

        $slug = trim($slug, '-');

        return $slug;
    }
}
