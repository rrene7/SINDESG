<?php
declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Maps a filesystem entrypoint to its browser URL prefix without depending
 * on Windows paths, Apache/XAMPP, domain names or a forwarded Host header.
 *
 * "legacy" layout: the project root is the document root (/SINDESG/).
 * "public" layout: only project/public is served (Linux production).
 */
final class WebPaths
{
    public function __construct(
        public readonly string $basePath,
        public readonly bool $publicDocumentRoot
    ) {}

    /** @param array<string,mixed> $server */
    public static function fromServer(array $server, string $root, string $configured = 'auto'): self
    {
        $rootPath = rtrim(str_replace('\\', '/', $root), '/');
        $scriptPath = str_replace('\\', '/', (string) ($server['SCRIPT_FILENAME'] ?? ''));
        $name = str_replace('\\', '/', (string) ($server['SCRIPT_NAME'] ?? ''));

        // SCRIPT_FILENAME is set by the web server to the physical PHP entrypoint.
        // A direct public/api entrypoint also uses the secure public layout.
        $public = $scriptPath !== '' && str_starts_with(
            strtolower($scriptPath),
            strtolower($rootPath . '/public/')
        );
        $base = '/SINDESG'; // stable CLI fallback; never used to infer a web path.

        if ($name !== '' && str_starts_with($name, '/')) {
            $name = strtok($name, '?') ?: $name;
            if (preg_match('~/index\.php$~i', $name)) {
                $base = substr($name, 0, -strlen('/index.php'));
            } elseif (preg_match('~/(?:public/)?api/[^/]+\.php$~i', $name)) {
                $base = (string) preg_replace('~/(?:public/)?api/[^/]+\.php$~i', '', $name);
            }
        }

        if ($configured !== 'auto') {
            $base = $configured;
        }
        if ($base === '/') {
            $base = '';
        }
        $base = rtrim($base, '/');
        if ($base !== '' && (!str_starts_with($base, '/')
            || !preg_match('~^/(?:[A-Za-z0-9_.~-]+)(?:/[A-Za-z0-9_.~-]+)*$~D', $base)
            || str_contains($base, '/..') || str_contains($base, '/./'))) {
            throw new InvalidArgumentException('base_url debe ser auto, / o una ruta absoluta sin dominio.');
        }
        return new self($base, $public);
    }

    public function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        // Historic controllers use public/api/... and require the same URL on
        // Windows root deployment and Linux public/ document-root deployment.
        if ($this->publicDocumentRoot && str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }
        return $this->basePath . '/' . $path;
    }

    public function asset(string $path): string
    {
        return $this->url(($this->publicDocumentRoot ? '' : 'public/') . 'assets/' . ltrim($path, '/'));
    }

    public function uploaded(string $relative): string
    {
        return $this->url('storage/uploads/' . ltrim($relative, '/'));
    }
}
