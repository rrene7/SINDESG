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
        $description = $this->nullableText($input['description'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('El nombre de la categoría es obligatorio.');
        }

        $baseSlug = $this->slugify($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->repository->categorySlugExists($slug)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;

            if ($suffix > 9999) {
                throw new InvalidArgumentException('No fue posible generar un identificador único para la categoría.');
            }
        }

        return $this->repository->createCategory($slug, $name, $description);
    }

    public function createProduct(array $input, ?array $image): array
    {
        $categoryId = (int) ($input['category_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $description = $this->nullableText($input['description'] ?? null);
        $sex = trim((string) ($input['sex'] ?? 'no_aplica'));
        $color = $this->nullableText($input['color'] ?? null);
        $initialSize = trim((string) ($input['initial_size_value'] ?? ''));
        $initialBrand = trim((string) ($input['initial_brand_name'] ?? ''));
        $initialInstitutionalCode = $this->nullableText($input['initial_institutional_code'] ?? null);
        $initialSupplierCode = $this->nullableText($input['initial_supplier_code'] ?? null);
        $initialVariantColor = $this->nullableText($input['initial_variant_color'] ?? null);

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

        if (strlen($initialSize) > 40) {
            throw new InvalidArgumentException('La talla inicial es demasiado larga.');
        }

        if (strlen($initialBrand) > 120) {
            throw new InvalidArgumentException('La marca inicial es demasiado larga.');
        }

        $validatedImage = $this->validateImage($image);

        $productId = $this->repository->createProduct([
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'sex' => $sex,
            'color' => $color,
            'initial_size_value' => $initialSize,
            'initial_brand_name' => $initialBrand,
            'initial_institutional_code' => $initialInstitutionalCode,
            'initial_supplier_code' => $initialSupplierCode,
            'initial_variant_color' => $initialVariantColor,
        ]);

        $warning = null;

        if ($validatedImage !== null) {
            try {
                $this->persistProductImage($productId, $validatedImage);
            } catch (\Throwable $exception) {
                $warning = 'El artículo fue creado, pero la imagen no pudo guardarse. Puede cargarla desde el detalle del artículo.';
            }
        }

        return ['id' => $productId, 'image_warning' => $warning];
    }

    public function replaceProductImage(int $productId, ?array $image): void
    {
        if ($productId <= 0) {
            throw new InvalidArgumentException('El artículo seleccionado no es válido.');
        }

        $validatedImage = $this->validateImage($image);

        if ($validatedImage === null) {
            throw new InvalidArgumentException('Seleccione una imagen.');
        }

        $this->persistProductImage($productId, $validatedImage);
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

    /** @return array{category_id:int,created:array<string,int>,existing:array<string,int>} */
    public function prepareFootwearCatalog(): array
    {
        return $this->repository->prepareFootwearCatalog();
    }

    /**
     * Solo tallas que se indiquen explícitamente; no se presumen del Excel.
     * Use coma, espacios, salto de línea o punto y coma para separar.
     * Medias tallas usan punto: 8.5 (no 8,5).
     *
     * @return list<string>
     */
    public function parseNumericSizes(string $input): array
    {
        $raw = preg_split('/[\s,;]+/u', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        if (!$raw || count($raw) > 40) {
            throw new InvalidArgumentException('Indique entre 1 y 40 tallas, sin usar coma decimal.');
        }
        $result = [];
        foreach ($raw as $value) {
            if (!preg_match('/^\d{1,2}(?:\.5)?$/', $value)) {
                throw new InvalidArgumentException(
                    'Talla no válida: ' . $value . '. Use 8 o 8.5, por ejemplo.'
                );
            }
            $n = (float) $value;
            if ($n < 3 || $n > 20) {
                throw new InvalidArgumentException(
                    'La talla ' . $value . ' está fuera del rango 3 a 20.'
                );
            }
            $key = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
            $result[$key] = $key;
        }
        return array_values($result);
    }

    /** @return array{created:list<string>,existing:list<string>} */
    public function createNumericSizesBulk(int $productId, string $input): array
    {
        return $this->repository->addNumericSizesBulk(
            $productId,
            $this->parseNumericSizes($input)
        );
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

    private function persistProductImage(int $productId, array $image): void
    {
        $stored = $this->storeImage($image);

        try {
            $this->repository->addProductImage(
                $productId,
                $stored['path'],
                $image['original_name'],
                $image['mime_type'],
                $image['size_bytes']
            );
        } catch (\Throwable $exception) {
            if (is_file($stored['absolute_path'])) {
                @unlink($stored['absolute_path']);
            }
            throw $exception;
        }
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

        return [
            'path' => 'catalog/' . $filename,
            'absolute_path' => $destination,
        ];
    }
}
