<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CatalogRepository;
use InvalidArgumentException;

final class CatalogService
{
    public function __construct(private CatalogRepository $repository)
    {
    }

    public function createCategory(array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $description = $this->nullableText($input['description'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('El nombre de la categoría es obligatorio.');
        }

        if (!preg_match('/^[A-Z0-9_-]{2,20}$/', $code)) {
            throw new InvalidArgumentException(
                'El código debe tener entre 2 y 20 caracteres: letras, números, guion o guion bajo.'
            );
        }

        if ($this->repository->categoryCodeExists($code)) {
            throw new InvalidArgumentException('Ya existe una categoría con ese código.');
        }

        $slug = $this->slugify($name);
        if ($this->repository->categorySlugExists($slug)) {
            $slug .= '-' . strtolower($code);
        }

        return $this->repository->createCategory($code, $slug, $name, $description);
    }

    public function createProduct(array $input, ?array $image): array
    {
        $categoryId = (int) ($input['category_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $description = $this->nullableText($input['description'] ?? null);
        $sex = trim((string) ($input['sex'] ?? 'no_aplica'));
        $color = $this->nullableText($input['color'] ?? null);

        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Seleccione una categoría.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('El nombre del artículo es obligatorio.');
        }

        $allowedSex = ['no_aplica', 'unisex', 'masculino', 'femenino'];
        if (!in_array($sex, $allowedSex, true)) {
            throw new InvalidArgumentException('El valor de sexo/aplicación no es válido.');
        }

        $validatedImage = $this->validateImage($image);

        $productId = $this->repository->createProduct([
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'sex' => $sex,
            'color' => $color,
        ]);

        $warning = null;

        if ($validatedImage !== null) {
            try {
                $stored = $this->storeImage($validatedImage);
                $this->repository->addProductImage(
                    $productId,
                    $stored['path'],
                    $validatedImage['original_name'],
                    $validatedImage['mime_type'],
                    $validatedImage['size_bytes']
                );
            } catch (\Throwable $exception) {
                $warning = 'El artículo fue creado, pero la imagen no pudo guardarse. Puede cargarse posteriormente.';
            }
        }

        return ['id' => $productId, 'image_warning' => $warning];
    }

    public function createVariant(int $productId, array $input): int
    {
        if ($productId <= 0) {
            throw new InvalidArgumentException('El artículo seleccionado no es válido.');
        }

        $sizeName = trim((string) ($input['size_value'] ?? ''));
        $brandName = trim((string) ($input['brand_name'] ?? ''));
        $institutionalCode = $this->nullableText($input['institutional_code'] ?? null);
        $supplierCode = $this->nullableText($input['supplier_code'] ?? null);
        $color = $this->nullableText($input['color'] ?? null);

        if (
            $sizeName === ''
            && $brandName === ''
            && $institutionalCode === null
            && $supplierCode === null
            && $color === null
        ) {
            throw new InvalidArgumentException('Indique al menos una característica para la variante.');
        }

        if (strlen($sizeName) > 40) {
            throw new InvalidArgumentException('La talla es demasiado larga.');
        }

        if (strlen($brandName) > 120) {
            throw new InvalidArgumentException('La marca es demasiado larga.');
        }

        $sizeId = $this->repository->findOrCreateSize($sizeName);
        $brandId = $this->repository->findOrCreateBrand($brandName);

        return $this->repository->createVariant([
            'product_id' => $productId,
            'size_id' => $sizeId,
            'brand_id' => $brandId,
            'institutional_code' => $institutionalCode,
            'supplier_code' => $supplierCode,
            'color' => $color,
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function slugify(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = is_string($normalized) ? $normalized : $value;
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'categoria';
    }

    private function validateImage(?array $image): ?array
    {
        if ($image === null || ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($image['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('La imagen no pudo cargarse correctamente.');
        }

        $tmpName = (string) ($image['tmp_name'] ?? '');
        $sizeBytes = (int) ($image['size'] ?? 0);
        $originalName = trim((string) ($image['name'] ?? 'imagen'));

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('El archivo de imagen no es una carga válida.');
        }

        if ($sizeBytes <= 0 || $sizeBytes > 6 * 1024 * 1024) {
            throw new InvalidArgumentException('La imagen debe pesar como máximo 6 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpName);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mimeType])) {
            throw new InvalidArgumentException('Solo se permiten imágenes JPG, PNG o WEBP.');
        }

        return [
            'tmp_name' => $tmpName,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'extension' => $allowed[$mimeType],
        ];
    }

    private function storeImage(array $image): array
    {
        $directory = SINDESG_ROOT . '/storage/uploads/catalog';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo crear el directorio de imágenes.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $image['extension'];
        $destination = $directory . '/' . $filename;

        if (!move_uploaded_file($image['tmp_name'], $destination)) {
            throw new \RuntimeException('No se pudo mover la imagen cargada.');
        }

        return ['path' => 'catalog/' . $filename];
    }
}
