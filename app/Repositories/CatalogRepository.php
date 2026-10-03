<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class CatalogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listCategories(): array
    {
        $sql = <<<'SQL'
            SELECT c.id, c.code, c.slug, c.name, c.description, c.sort_order, c.is_active,
                   COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND p.status <> 'deleted'
            GROUP BY c.id
            ORDER BY c.sort_order, c.name
        SQL;

        return $this->pdo->query($sql)->fetchAll();
    }

    public function stats(): array
    {
        $sql = <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM categories WHERE is_active = 1) AS categories,
                (SELECT COUNT(*) FROM products WHERE status = 'active') AS products,
                (SELECT COUNT(*) FROM product_variants WHERE status = 'active') AS variants
        SQL;

        $row = $this->pdo->query($sql)->fetch();

        return [
            'categories' => (int) ($row['categories'] ?? 0),
            'products' => (int) ($row['products'] ?? 0),
            'variants' => (int) ($row['variants'] ?? 0),
        ];
    }

    public function listProducts(?int $categoryId = null, string $search = ''): array
    {
        $conditions = ["p.status <> 'deleted'"];
        $params = [];

        if ($categoryId !== null && $categoryId > 0) {
            $conditions[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $search = trim($search);
        if ($search !== '') {
            $conditions[] = '(p.name LIKE :search_name OR p.internal_code LIKE :search_code OR c.name LIKE :search_category)';
            $like = '%' . $search . '%';
            $params['search_name'] = $like;
            $params['search_code'] = $like;
            $params['search_category'] = $like;
        }

        $sql = '
            SELECT p.id, p.internal_code, p.name, p.description, p.sex, p.color, p.status, p.created_at,
                   c.id AS category_id, c.name AS category_name, c.code AS category_code,
                   pi.path AS primary_image,
                   COUNT(DISTINCT pv.id) AS variant_count
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
            LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.status <> "deleted"
            WHERE ' . implode(' AND ', $conditions) . '
            GROUP BY p.id, pi.path
            ORDER BY p.created_at DESC, p.id DESC
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function getProduct(int $productId): ?array
    {
        $sql = <<<'SQL'
            SELECT p.*, c.name AS category_name, c.code AS category_code, c.slug AS category_slug,
                   pi.path AS primary_image
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
            WHERE p.id = :id AND p.status <> 'deleted'
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $productId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function variantsForProduct(int $productId): array
    {
        $sql = <<<'SQL'
            SELECT pv.id, pv.internal_code, pv.institutional_code, pv.supplier_code, pv.color, pv.status,
                   s.name AS size_name, s.code AS size_code, b.name AS brand_name
            FROM product_variants pv
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE pv.product_id = :product_id AND pv.status <> 'deleted'
            ORDER BY s.sort_order, s.name, b.name, pv.id
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['product_id' => $productId]);

        return $statement->fetchAll();
    }

    public function listSizes(): array
    {
        return $this->pdo->query(
            'SELECT id, code, name FROM sizes WHERE is_active = 1 ORDER BY sort_order, name'
        )->fetchAll();
    }

    public function listBrands(): array
    {
        return $this->pdo->query(
            'SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    public function categoryCodeExists(string $code): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM categories WHERE code = :code LIMIT 1');
        $statement->execute(['code' => $code]);
        return (bool) $statement->fetchColumn();
    }

    public function categorySlugExists(string $slug): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM categories WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        return (bool) $statement->fetchColumn();
    }

    public function createCategory(string $code, string $slug, string $name, ?string $description): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO categories (code, slug, name, description, sort_order, is_active)
             VALUES (:code, :slug, :name, :description, 900, 1)'
        );
        $statement->execute([
            'code' => $code,
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createProduct(array $data): int
    {
        $this->pdo->beginTransaction();

        try {
            $categoryStatement = $this->pdo->prepare(
                'SELECT code FROM categories WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $categoryStatement->execute(['id' => $data['category_id']]);
            $categoryCode = $categoryStatement->fetchColumn();

            if (!is_string($categoryCode) || $categoryCode === '') {
                throw new RuntimeException('La categoría seleccionada no está disponible.');
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO products
                    (category_id, internal_code, name, description, sex, color, status)
                 VALUES
                    (:category_id, NULL, :name, :description, :sex, :color, "active")'
            );
            $statement->execute([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'sex' => $data['sex'],
                'color' => $data['color'],
            ]);

            $productId = (int) $this->pdo->lastInsertId();
            $internalCode = sprintf('SND-%s-%06d', strtoupper($categoryCode), $productId);

            $update = $this->pdo->prepare(
                'UPDATE products SET internal_code = :internal_code WHERE id = :id'
            );
            $update->execute(['internal_code' => $internalCode, 'id' => $productId]);

            $this->pdo->commit();
            return $productId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function addProductImage(
        int $productId,
        string $path,
        string $originalName,
        string $mimeType,
        int $sizeBytes
    ): void {
        $primaryStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM product_images WHERE product_id = :product_id AND is_primary = 1'
        );
        $primaryStatement->execute(['product_id' => $productId]);
        $isPrimary = ((int) $primaryStatement->fetchColumn()) === 0 ? 1 : 0;

        $statement = $this->pdo->prepare(
            'INSERT INTO product_images
                (product_id, path, original_name, mime_type, size_bytes, is_primary)
             VALUES
                (:product_id, :path, :original_name, :mime_type, :size_bytes, :is_primary)'
        );
        $statement->execute([
            'product_id' => $productId,
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'is_primary' => $isPrimary,
        ]);
    }

    public function findOrCreateSize(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $code = strtoupper($name);
        $find = $this->pdo->prepare('SELECT id FROM sizes WHERE code = :code LIMIT 1');
        $find->execute(['code' => $code]);
        $existing = $find->fetchColumn();

        if ($existing !== false) {
            return (int) $existing;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO sizes (code, name, sort_order, is_active) VALUES (:code, :name, 900, 1)'
        );
        $insert->execute(['code' => $code, 'name' => $name]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findOrCreateBrand(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $find = $this->pdo->prepare('SELECT id FROM brands WHERE name = :name LIMIT 1');
        $find->execute(['name' => $name]);
        $existing = $find->fetchColumn();

        if ($existing !== false) {
            return (int) $existing;
        }

        $insert = $this->pdo->prepare('INSERT INTO brands (name, is_active) VALUES (:name, 1)');
        $insert->execute(['name' => $name]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createVariant(array $data): int
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO product_variants
                    (product_id, size_id, brand_id, internal_code, institutional_code, supplier_code, color, status)
                 VALUES
                    (:product_id, :size_id, :brand_id, NULL, :institutional_code, :supplier_code, :color, "active")'
            );
            $statement->execute([
                'product_id' => $data['product_id'],
                'size_id' => $data['size_id'],
                'brand_id' => $data['brand_id'],
                'institutional_code' => $data['institutional_code'],
                'supplier_code' => $data['supplier_code'],
                'color' => $data['color'],
            ]);

            $variantId = (int) $this->pdo->lastInsertId();
            $internalCode = sprintf('SND-V-%07d', $variantId);

            $update = $this->pdo->prepare(
                'UPDATE product_variants SET internal_code = :internal_code WHERE id = :id'
            );
            $update->execute(['internal_code' => $internalCode, 'id' => $variantId]);

            $this->pdo->commit();
            return $variantId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
