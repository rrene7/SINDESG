<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CatalogRepository;
use App\Repositories\InventoryRepository;
use App\Services\CatalogService;
use InvalidArgumentException;
use PDO;
use Throwable;

final class InventoryController
{
    private CatalogRepository $repository;
    private InventoryRepository $inventory;
    private CatalogService $service;

    public function __construct(PDO $pdo)
    {
        $this->repository = new CatalogRepository($pdo);
        $this->inventory = new InventoryRepository($pdo);
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
                    'catalogError' => 'El inventario todavía no está disponible en esta instalación. Aplique las migraciones 002_catalog.sql y 003_inventory_core.sql y vuelva a intentarlo.',
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
            $returnTo = (string) ($query['return_to'] ?? 'inventory');
            if (!in_array($returnTo, ['inventory', 'receipts', 'production'], true)) {
                $returnTo = 'inventory';
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/inventory/product-form.php',
                'title' => 'Nuevo artículo',
                'data' => [
                    'catalogCategories' => $this->repository->listCategories(),
                    'returnTo' => $returnTo,
                ],
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

            $variants = $this->repository->variantsForProduct($productId);
            $stockRows = $this->inventory->stockByProduct($productId);
            $stockMap = [];

            foreach ($stockRows as $stockRow) {
                $stockMap[(int) $stockRow['variant_id']] = $stockRow;
            }

            foreach ($variants as &$variant) {
                $stock = $stockMap[(int) $variant['id']] ?? null;
                $variant['stock_total'] = $stock['stock_total'] ?? 0;
                $variant['stock_central'] = $stock['stock_central'] ?? 0;
                $variant['stock_committed'] = $stock['stock_committed'] ?? 0;
                $variant['stock_available'] = $stock['stock_available'] ?? 0;
                $variant['stock_p4'] = $stock['stock_p4'] ?? 0;
                $variant['stock_transit'] = $stock['stock_transit'] ?? 0;
            }
            unset($variant);

            return [
                'view' => SINDESG_ROOT . '/app/Views/inventory/product-show.php',
                'title' => (string) $product['name'],
                'data' => [
                    'product' => $product,
                    'variants' => $variants,
                    'sizes' => $this->repository->listSizes(),
                    'brands' => $this->repository->listBrands(),
                    'recentMovements' => $this->inventory->recentMovementsForProduct($productId),
                    'warehouses' => $this->inventory->warehouses(),
                ],
            ];
        }

        $categoryId = isset($query['category_id']) ? (int) $query['category_id'] : null;
        $search = trim((string) ($query['q'] ?? ''));
        $products = $this->repository->listProducts($categoryId, $search);
        $productStock = $this->inventory->productStockMap();

        foreach ($products as &$product) {
            $stock = $productStock[(int) $product['id']] ?? [];
            $product['stock_total'] = $stock['stock_total'] ?? 0;
            $product['stock_central'] = $stock['stock_central'] ?? 0;
            $product['stock_committed'] = $stock['stock_committed'] ?? 0;
            $product['stock_available'] = $stock['stock_available'] ?? 0;
            $product['stock_p4'] = $stock['stock_p4'] ?? 0;
            $product['stock_transit'] = $stock['stock_transit'] ?? 0;
        }
        unset($product);

        return [
            'view' => SINDESG_ROOT . '/app/Views/inventory/index.php',
            'title' => 'Inventario',
            'data' => [
                'catalogStats' => $this->repository->stats(),
                'inventoryStats' => $this->inventory->overview(),
                'catalogCategories' => $this->repository->listCategories(),
                'products' => $products,
                'selectedCategoryId' => $categoryId,
                'catalogSearch' => $search,
            ],
        ];
    }

    private function handlePost(string $action, array $post, array $files): array
    {
        if ($action === 'prepare-footwear') {
            if ((string) ($post['confirm_footwear'] ?? '') !== 'yes') {
                throw new InvalidArgumentException('Confirme que desea preparar el catálogo base de calzado.');
            }
            $result = $this->service->prepareFootwearCatalog();
            flash('success',
                'Calzado preparado: ' . count($result['created']) . ' artículos nuevos; '
                . count($result['existing']) . ' existentes reutilizados. '
                . 'Agregue las tallas verificadas desde cada artículo. Inventario intacto.'
            );
            $this->redirect('?module=inventory');
        }

        if ($action === 'save-sizes-bulk') {
            $productId = (int) ($post['product_id'] ?? 0);
            $result = $this->service->createNumericSizesBulk(
                $productId,
                (string) ($post['sizes_bulk'] ?? '')
            );
            flash('success',
                count($result['created']) . ' tallas nuevas; '
                . count($result['existing']) . ' tallas ya registradas. '
                . 'Sin movimientos de inventario.'
            );
            $this->redirect('?module=inventory&action=product&id=' . $productId);
        }

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

            if (($post['return_to'] ?? '') === 'receipts') {
                flash('success', 'Artículo y primera variante listos para usar en la recepción.');
                $this->redirect('?module=receipts&action=new');
            }

            if (($post['return_to'] ?? '') === 'production') {
                flash('success', 'Artículo y primera variante listos para usar en producción.');
                $this->redirect('?module=production&action=new');
            }

            $this->redirect('?module=inventory&action=product&id=' . $result['id']);
        }

        if ($action === 'save-image') {
            $productId = (int) ($post['product_id'] ?? 0);
            $this->service->replaceProductImage($productId, $files['main_image'] ?? null);
            flash('success', 'Fotografía principal actualizada.');
            $this->redirect('?module=inventory&action=product&id=' . $productId);
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
