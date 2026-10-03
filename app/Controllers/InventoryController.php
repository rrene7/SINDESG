<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CatalogRepository;
use App\Services\CatalogService;
use InvalidArgumentException;
use PDO;
use Throwable;

final class InventoryController
{
    private CatalogRepository $repository;
    private CatalogService $service;

    public function __construct(PDO $pdo)
    {
        $this->repository = new CatalogRepository($pdo);
        $this->service = new CatalogService($this->repository);
    }

    public function handle(
        string $action,
        array $query,
        array $post,
        array $files,
        string $method
    ): array {
        try {
            if (strtoupper($method) === 'POST') {
                if (!csrf_valid(isset($post['_token']) ? (string) $post['_token'] : null)) {
                    throw new InvalidArgumentException('La sesión del formulario venció. Intente nuevamente.');
                }

                return $this->handlePost($action, $post, $files);
            }

            return $this->handleGet($action, $query);
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            $fallback = isset($post['product_id']) && (int) $post['product_id'] > 0
                ? '?module=inventory&action=product&id=' . (int) $post['product_id']
                : '?module=inventory';
            $this->redirect($fallback);
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/inventory/unavailable.php',
                'title' => 'Inventario',
                'data' => [
                    'catalogError' => 'El catálogo todavía no está disponible en esta instalación. Aplique la migración 002_catalog.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'new-category') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/inventory/category-form.php',
                'title' => 'Nueva categoría',
                'data' => [],
            ];
        }

        if ($action === 'new-product') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/inventory/product-form.php',
                'title' => 'Nuevo artículo',
                'data' => ['catalogCategories' => $this->repository->listCategories()],
            ];
        }

        if ($action === 'product') {
            $productId = (int) ($query['id'] ?? 0);
            $product = $this->repository->getProduct($productId);

            if ($product === null) {
                return [
                    'view' => SINDESG_ROOT . '/app/Views/inventory/unavailable.php',
                    'title' => 'Artículo no encontrado',
                    'data' => ['catalogError' => 'El artículo solicitado no existe o ya no está disponible.'],
                ];
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/inventory/product-show.php',
                'title' => (string) $product['name'],
                'data' => [
                    'product' => $product,
                    'variants' => $this->repository->variantsForProduct($productId),
                    'sizes' => $this->repository->listSizes(),
                    'brands' => $this->repository->listBrands(),
                ],
            ];
        }

        $categoryId = isset($query['category_id']) ? (int) $query['category_id'] : null;
        $search = trim((string) ($query['q'] ?? ''));

        return [
            'view' => SINDESG_ROOT . '/app/Views/inventory/index.php',
            'title' => 'Inventario',
            'data' => [
                'catalogStats' => $this->repository->stats(),
                'catalogCategories' => $this->repository->listCategories(),
                'products' => $this->repository->listProducts($categoryId, $search),
                'selectedCategoryId' => $categoryId,
                'catalogSearch' => $search,
            ],
        ];
    }

    private function handlePost(string $action, array $post, array $files): array
    {
        if ($action === 'save-category') {
            $this->service->createCategory($post);
            flash('success', 'Categoría creada correctamente.');
            $this->redirect('?module=inventory');
        }

        if ($action === 'save-product') {
            $result = $this->service->createProduct($post, $files['main_image'] ?? null);
            flash('success', 'Artículo creado correctamente.');

            if ($result['image_warning'] !== null) {
                flash('warning', $result['image_warning']);
            }

            $this->redirect('?module=inventory&action=product&id=' . $result['id']);
        }

        if ($action === 'save-variant') {
            $productId = (int) ($post['product_id'] ?? 0);
            $this->service->createVariant($productId, $post);
            flash('success', 'Variante agregada correctamente.');
            $this->redirect('?module=inventory&action=product&id=' . $productId);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
