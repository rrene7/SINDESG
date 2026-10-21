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
            ORDER BY c.sort_order, c.id
        SQL;

        return $this->pdo->query($sql)->fetchAll();
    }

    /** Only active categories are offered for new stock-bearing products. */
    public function listActiveCategories(): array
    {
        return array_values(array_filter(
            $this->listCategories(),
            static fn (array $category): bool => (int) $category['is_active'] === 1
        ));
    }

    public function getCategory(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.code, c.slug, c.name, c.description, c.sort_order, c.is_active,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS all_product_count
             FROM categories c WHERE c.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Update public metadata; institutional code, slug and product relations never change. */
    public function updateCategoryAsAdmin(int $id, array $input, array $actor): void
    {
        $this->pdo->beginTransaction();
        try {
            $before = $this->lockCategory($id);
            if ($before === null) {
                throw new \InvalidArgumentException('La categoría no existe.');
            }
            $duplicate = $this->pdo->prepare(
                'SELECT id FROM categories
                 WHERE LOWER(TRIM(name)) = LOWER(:name) AND id <> :id AND is_active = 1
                 LIMIT 1'
            );
            $duplicate->execute(['name' => $input['name'], 'id' => $id]);
            if ($duplicate->fetchColumn() !== false) {
                throw new \InvalidArgumentException('Ya existe otra categoría activa con ese nombre.');
            }
            $this->pdo->prepare(
                'UPDATE categories SET name = :name, description = :description,
                 sort_order = :sort_order WHERE id = :id'
            )->execute([
                'name' => $input['name'],
                'description' => $input['description'],
                'sort_order' => $input['sort_order'],
                'id' => $id,
            ]);
            $this->auditCategory($actor, 'category_updated', $id, $before, [
                'code' => $before['code'],
                'slug' => $before['slug'],
                'name' => $input['name'],
                'description' => $input['description'],
                'sort_order' => $input['sort_order'],
                'is_active' => (int) $before['is_active'],
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * Archive (= remove from active catalog) or restore. No physical deletion:
     * preserve all original IDs and links for audit and institutional history.
     */
    public function setCategoryActiveAsAdmin(int $id, bool $active, string $reason, array $actor): void
    {
        $this->pdo->beginTransaction();
        try {
            $before = $this->lockCategory($id);
            if ($before === null) {
                throw new \InvalidArgumentException('La categoría no existe.');
            }
            if ((int) $before['is_active'] === (int) $active) {
                throw new \InvalidArgumentException('La categoría ya tiene ese estado.');
            }
            if (!$active) {
                $count = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM products WHERE category_id = :id'
                );
                $count->execute(['id' => $id]);
                // Includes active, inactive AND historically deleted products.
                // Keeping a category with references is safer than hiding stock.
                if ((int) $count->fetchColumn() > 0) {
                    throw new \InvalidArgumentException(
                        'No se puede eliminar del catálogo: existen artículos vinculados, incluso históricos. '
                        . 'Conserve o reorganice sus registros mediante un proceso auditado.'
                    );
                }
            } else {
                $duplicate = $this->pdo->prepare(
                    'SELECT id FROM categories
                     WHERE LOWER(TRIM(name)) = LOWER(:name) AND id <> :id AND is_active = 1
                     LIMIT 1'
                );
                $duplicate->execute(['name' => $before['name'], 'id' => $id]);
                if ($duplicate->fetchColumn() !== false) {
                    throw new \InvalidArgumentException(
                        'Ya existe una categoría activa con ese nombre; cambie el nombre antes de restaurar.'
                    );
                }
            }

            $this->pdo->prepare('UPDATE categories SET is_active = :active WHERE id = :id')
                ->execute(['active' => $active ? 1 : 0, 'id' => $id]);
            $after = $before;
            $after['is_active'] = $active ? 1 : 0;
            $this->auditCategory(
                $actor,
                $active ? 'category_restored' : 'category_archived',
                $id,
                $before,
                $after,
                ['reason' => $reason]
            );
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function lockCategory(int $id): ?array
    {
        $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE' : '';
        $stmt = $this->pdo->prepare(
            'SELECT id, code, slug, name, description, sort_order, is_active
             FROM categories WHERE id = :id' . $lock
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function auditCategory(
        array $actor,
        string $action,
        int $categoryId,
        ?array $before,
        array $after,
        ?array $details = null
    ): void {
        (new AuditRepository($this->pdo))->log([
            'user_id' => $actor['id'] ?? null,
            'user_label_snapshot' => $actor['display_name'] ?? null,
            'module_code' => 'inventory',
            'action_code' => $action,
            'record_type' => 'category',
            'record_id' => (string) $categoryId,
            'before' => $before,
            'after' => $after,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
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

    public function listVariantOptions(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                pv.id,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                pv.supplier_code,
                p.name AS product_name,
                c.name AS category_name,
                c.sort_order AS category_sort_order,
                s.name AS size_name,
                b.name AS brand_name
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id AND p.status = 'active'
            INNER JOIN categories c ON c.id = p.category_id AND c.is_active = 1
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE pv.status = 'active'
            ORDER BY c.sort_order, c.id, p.name, s.sort_order, s.name, b.name, pv.id
        SQL)->fetchAll();
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

    public function categorySlugExists(string $slug): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM categories WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        return (bool) $statement->fetchColumn();
    }

    public function createCategory(string $slug, string $name, ?string $description, ?array $actor = null): int
    {
        $this->pdo->beginTransaction();

        try {
            $temporaryCode = 'TMP-' . strtoupper(bin2hex(random_bytes(6)));

            $statement = $this->pdo->prepare(
                'INSERT INTO categories (code, slug, name, description, sort_order, is_active)
                 VALUES (:code, :slug, :name, :description, 0, 1)'
            );
            $statement->execute([
                'code' => $temporaryCode,
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
            ]);

            $categoryId = (int) $this->pdo->lastInsertId();
            $code = sprintf('CAT-%06d', $categoryId);
            $sortOrder = $categoryId * 10;

            $update = $this->pdo->prepare(
                'UPDATE categories
                 SET code = :code, sort_order = :sort_order
                 WHERE id = :id'
            );
            $update->execute([
                'code' => $code,
                'sort_order' => $sortOrder,
                'id' => $categoryId,
            ]);

            if ($actor !== null) {
                $this->auditCategory($actor, 'category_created', $categoryId, null, [
                    'code' => $code,
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'is_active' => 1,
                ]);
            }
            $this->pdo->commit();
            return $categoryId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function createProduct(array $data): int
    {
        $this->pdo->beginTransaction();

        try {
            $categoryLock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE' : '';
            $categoryStatement = $this->pdo->prepare(
                'SELECT code FROM categories WHERE id = :id AND is_active = 1 LIMIT 1' . $categoryLock
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

            $sizeId = $this->findOrCreateSize((string) ($data['initial_size_value'] ?? ''));
            $brandId = $this->findOrCreateBrand((string) ($data['initial_brand_name'] ?? ''));

            $variantStatement = $this->pdo->prepare(
                'INSERT INTO product_variants
                    (product_id, size_id, brand_id, internal_code, institutional_code, supplier_code, color, status)
                 VALUES
                    (:product_id, :size_id, :brand_id, NULL, :institutional_code, :supplier_code, :color, "active")'
            );
            $variantStatement->execute([
                'product_id' => $productId,
                'size_id' => $sizeId,
                'brand_id' => $brandId,
                'institutional_code' => $data['initial_institutional_code'] ?? null,
                'supplier_code' => $data['initial_supplier_code'] ?? null,
                'color' => $data['initial_variant_color'] ?? null,
            ]);

            $variantId = (int) $this->pdo->lastInsertId();
            $variantCode = sprintf('SND-V-%07d', $variantId);

            $variantUpdate = $this->pdo->prepare(
                'UPDATE product_variants SET internal_code = :internal_code WHERE id = :id'
            );
            $variantUpdate->execute([
                'internal_code' => $variantCode,
                'id' => $variantId,
            ]);

            $this->pdo->commit();
            return $productId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Prepara un catálogo base de calzado SIN inventario ni variantes ficticias.
     * Crea solo lo que falte; reutiliza productos del mismo nombre ya registrados,
     * incluso si pertenecen a una categoría histórica (por ejemplo BOTAS).
     * Si existen homónimos ambiguos, interrumpe el proceso sin escribir nada.
     *
     * @return array{category_id:int,created:array<string,int>,existing:array<string,int>}
     */
    public function prepareFootwearCatalog(): array
    {
        $names = ['BOTAS TÁCTICAS', 'ZAPATOS DE CHAROL', 'ZAPATOS REGULARES'];
        $this->pdo->beginTransaction();
        try {
            $findCategory = $this->pdo->prepare(
                'SELECT id, is_active FROM categories
                 WHERE slug = :slug OR name = :name ORDER BY id FOR UPDATE'
            );
            $findCategory->execute(['slug' => 'calzado', 'name' => 'CALZADO']);
            $categories = $findCategory->fetchAll();
            if (count($categories) > 1) {
                throw new \InvalidArgumentException(
                    'Hay múltiples categorías llamadas Calzado. Concílielas antes de continuar.'
                );
            }
            if ($categories !== []) {
                if ((int) $categories[0]['is_active'] !== 1) {
                    throw new \InvalidArgumentException('La categoría Calzado existente está inactiva.');
                }
                $categoryId = (int) $categories[0]['id'];
            } else {
                $insertCategory = $this->pdo->prepare(
                    'INSERT INTO categories (code, slug, name, description, sort_order, is_active)
                     VALUES (:code, :slug, :name, :description, 0, 1)'
                );
                $insertCategory->execute([
                    'code' => 'TMP-' . strtoupper(bin2hex(random_bytes(6))),
                    'slug' => 'calzado',
                    'name' => 'CALZADO',
                    'description' => 'Dotación institucional de botas y zapatos.',
                ]);
                $categoryId = (int) $this->pdo->lastInsertId();
                $this->pdo->prepare(
                    'UPDATE categories SET code = :code, sort_order = :sort_order WHERE id = :id'
                )->execute([
                    'code' => sprintf('CAT-%06d', $categoryId),
                    'sort_order' => $categoryId * 10,
                    'id' => $categoryId,
                ]);
            }

            $findProduct = $this->pdo->prepare(
                'SELECT id, status FROM products WHERE TRIM(name) = :name
                 ORDER BY id FOR UPDATE'
            );
            $insertProduct = $this->pdo->prepare(
                'INSERT INTO products (category_id, internal_code, name, description,
                                      sex, color, status)
                 VALUES (:category_id, NULL, :name, :description,
                         :sex, NULL, "active")'
            );
            $updateCode = $this->pdo->prepare(
                'UPDATE products SET internal_code = :code WHERE id = :id'
            );
            $categoryCodeQuery = $this->pdo->prepare(
                'SELECT code FROM categories WHERE id = :id'
            );
            $categoryCodeQuery->execute(['id' => $categoryId]);
            $categoryCode = (string) $categoryCodeQuery->fetchColumn();
            $result = ['category_id' => $categoryId, 'created' => [], 'existing' => []];

            foreach ($names as $name) {
                $findProduct->execute(['name' => $name]);
                $matches = $findProduct->fetchAll();
                if (count($matches) > 1) {
                    throw new \InvalidArgumentException(
                        "Hay más de un artículo llamado {$name}. Debe conciliar los duplicados."
                    );
                }
                if ($matches !== []) {
                    if ($matches[0]['status'] !== 'active') {
                        throw new \InvalidArgumentException(
                            "El artículo {$name} existe, pero está inactivo. Revíselo."
                        );
                    }
                    $result['existing'][$name] = (int) $matches[0]['id'];
                    continue;
                }
                $insertProduct->execute([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'description' => 'Dotación institucional por talla. Modelo o código oficial por verificar.',
                    'sex' => 'no_aplica',
                ]);
                $id = (int) $this->pdo->lastInsertId();
                $updateCode->execute([
                    'code' => sprintf('SND-%s-%06d', strtoupper($categoryCode), $id),
                    'id' => $id,
                ]);
                $result['created'][$name] = $id;
            }
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Crea tallas numéricas sin repetir tallas existentes y sin modificar
     * kardex, stock ni datos históricos. Una sola transacción para el lote.
     *
     * @param list<string> $sizes
     * @return array{created:list<string>,existing:list<string>}
     */
    public function addNumericSizesBulk(int $productId, array $sizes): array
    {
        if ($productId <= 0 || $sizes === [] || count($sizes) > 40) {
            throw new \InvalidArgumentException('Seleccione un producto y de 1 a 40 tallas.');
        }

        $this->pdo->beginTransaction();
        try {
            $productQuery = $this->pdo->prepare(
                'SELECT id, status FROM products WHERE id = :id FOR UPDATE'
            );
            $productQuery->execute(['id' => $productId]);
            $product = $productQuery->fetch();
            if (!$product || $product['status'] !== 'active') {
                throw new \InvalidArgumentException('El artículo no existe o no está activo.');
            }

            $existingQuery = $this->pdo->prepare(
                'SELECT pv.id, pv.status, s.name AS size_name
                 FROM product_variants pv
                 INNER JOIN sizes s ON s.id = pv.size_id
                 WHERE pv.product_id = :product_id FOR UPDATE'
            );
            $existingQuery->execute(['product_id' => $productId]);
            $existing = [];
            foreach ($existingQuery->fetchAll() as $row) {
                $size = str_replace(',', '.', trim((string) $row['size_name']));
                if (preg_match('/^\d{1,2}(?:\.5)?$/', $size)) {
                    $canonical = rtrim(rtrim(number_format((float) $size, 1, '.', ''), '0'), '.');
                    $existing[$canonical][] = $row;
                }
            }
            foreach ($existing as $key => $found) {
                if (count($found) > 1) {
                    throw new \InvalidArgumentException(
                        "La talla {$key} ya tiene variantes duplicadas en el producto. Revise el catálogo."
                    );
                }
                if ($found[0]['status'] !== 'active') {
                    throw new \InvalidArgumentException(
                        "La talla {$key} existe inactiva. Debe revisarse antes de crear otra."
                    );
                }
            }

            $result = ['created' => [], 'existing' => []];
            $insert = $this->pdo->prepare(
                'INSERT INTO product_variants
                 (product_id, size_id, brand_id, internal_code,
                  institutional_code, supplier_code, color, status)
                 VALUES (:product_id, :size_id, NULL, NULL,
                         NULL, NULL, NULL, "active")'
            );
            $setCode = $this->pdo->prepare(
                'UPDATE product_variants SET internal_code = :code WHERE id = :id'
            );

            foreach ($sizes as $size) {
                if (isset($existing[$size])) {
                    $result['existing'][] = $size;
                    continue;
                }
                $sizeId = $this->findOrCreateSize($size);
                if ($sizeId === null) {
                    throw new \InvalidArgumentException('No se pudo registrar talla ' . $size);
                }
                $insert->execute(['product_id' => $productId, 'size_id' => $sizeId]);
                $variantId = (int) $this->pdo->lastInsertId();
                $setCode->execute([
                    'code' => sprintf('SND-V-%07d', $variantId),
                    'id' => $variantId,
                ]);
                $result['created'][] = $size;
            }
            $this->pdo->commit();
            return $result;
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
        $this->pdo->beginTransaction();

        try {
            $clear = $this->pdo->prepare(
                'UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id'
            );
            $clear->execute(['product_id' => $productId]);

            $statement = $this->pdo->prepare(
                'INSERT INTO product_images
                    (product_id, path, original_name, mime_type, size_bytes, is_primary)
                 VALUES
                    (:product_id, :path, :original_name, :mime_type, :size_bytes, 1)'
            );
            $statement->execute([
                'product_id' => $productId,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
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
