<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Regla institucional exclusivamente para NOMINACIONES/SOLICITUDES P4.
 * Todas las condiciones administrativas admitidas salvo:
 * - Vacaciones por jubilación (código oficial RRHH 15);
 * - Inactivo / estados de inactividad.
 *
 * Nunca aplicar esta excepción a entrega física, firma, autorización
 * de despachos o cambios de estatus de RRHH2029.
 */
final class P4NominalStatusPolicy
{
    /** @param array<string,mixed> $rrhh */
    public static function isAllowed(array $rrhh): bool
    {
        if ((int) ($rrhh['status_is_inactive'] ?? 0) === 1) {
            return false;
        }
        if ((int) ($rrhh['status_is_active'] ?? 1) === 0) {
            return false;
        }
        if (trim((string) ($rrhh['status_legacy_code'] ?? '')) === '15') {
            return false;
        }
        $status = self::canonical((string) ($rrhh['status_name'] ?? ''));
        if (preg_match('/^INACTIV[OA]S?\b/', $status)) {
            return false;
        }
        if (str_contains($status, 'VACACION')
            && str_contains($status, 'JUBILACION')) {
            return false;
        }
        // blocks_actions puede estar activado para permisos, licencias,
        // incapacidades y otros estados que el P4 SÍ puede nominar.
        return true;
    }

    /** @param array<string,mixed> $rrhh */
    public static function rejectionReason(array $rrhh): ?string
    {
        if (self::isAllowed($rrhh)) {
            return null;
        }
        $status = trim((string) ($rrhh['status_name'] ?? ''));
        return 'Estado RRHH excluido para dotación P4: '
            . ($status !== '' ? $status : 'inactivo / jubilación')
            . '. No puede incluirse automáticamente en el borrador.';
    }

    private static function canonical(string $text): string
    {
        $text = mb_strtoupper(trim($text), 'UTF-8');
        $text = strtr($text, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O',
            'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        return preg_replace('/\s+/u', ' ', $text) ?? '';
    }
}
