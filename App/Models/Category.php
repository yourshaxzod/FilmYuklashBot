<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Config;

class Category
{
    public static function getAll(PDO $db, ?int $limit = null, int $offset = 0): array
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

    public static function findById(PDO $db, int $id): ?array
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

    public static function findBySlug(PDO $db, string $slug): ?array
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

    public static function findByName(PDO $db, string $name): ?array
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

    public static function create(PDO $db, array $data): int
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

    public static function update(PDO $db, int $id, array $data): void
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

    public static function delete(PDO $db, int $id): void
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

    public static function getCount(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM categories");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Kategoriyalar sonini olishda xatolik: " . $e->getMessage());
        }
    }

    public static function getByMovieId(PDO $db, int $movieId): array
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

    public static function saveMovieCategories(PDO $db, int $movieId, array $categoryIds): void
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

    public static function getUserTopCategories(PDO $db, int $userId, int $limit = 5): array
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

    public static function updateUserInterest(PDO $db, int $userId, int $categoryId, float $increment = 0.0): void
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

    private static function createSlug(string $name): string
    {
        $cyrillic = [
            'а',
            'б',
            'в',
            'г',
            'д',
            'е',
            'ё',
            'ж',
            'з',
            'и',
            'й',
            'к',
            'л',
            'м',
            'н',
            'о',
            'п',
            'р',
            'с',
            'т',
            'у',
            'ф',
            'х',
            'ц',
            'ч',
            'ш',
            'щ',
            'ъ',
            'ы',
            'ь',
            'э',
            'ю',
            'я',
            'А',
            'Б',
            'В',
            'Г',
            'Д',
            'Е',
            'Ё',
            'Ж',
            'З',
            'И',
            'Й',
            'К',
            'Л',
            'М',
            'Н',
            'О',
            'П',
            'Р',
            'С',
            'Т',
            'У',
            'Ф',
            'Х',
            'Ц',
            'Ч',
            'Ш',
            'Щ',
            'Ъ',
            'Ы',
            'Ь',
            'Э',
            'Ю',
            'Я',
            'ў',
            'қ',
            'ғ',
            'ҳ',
            'Ў',
            'Қ',
            'Ғ',
            'Ҳ'
        ];
        $latin = [
            'a',
            'b',
            'v',
            'g',
            'd',
            'e',
            'yo',
            'j',
            'z',
            'i',
            'y',
            'k',
            'l',
            'm',
            'n',
            'o',
            'p',
            'r',
            's',
            't',
            'u',
            'f',
            'h',
            'ts',
            'ch',
            'sh',
            'sch',
            '',
            'i',
            '',
            'e',
            'yu',
            'ya',
            'A',
            'B',
            'V',
            'G',
            'D',
            'E',
            'Yo',
            'J',
            'Z',
            'I',
            'Y',
            'K',
            'L',
            'M',
            'N',
            'O',
            'P',
            'R',
            'S',
            'T',
            'U',
            'F',
            'H',
            'Ts',
            'Ch',
            'Sh',
            'Sch',
            '',
            'I',
            '',
            'E',
            'Yu',
            'Ya',
            'o',
            'q',
            'g',
            'h',
            'O',
            'Q',
            'G',
            'H'
        ];
        $name = str_replace($cyrillic, $latin, $name);

        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}
