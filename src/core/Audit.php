<?php
namespace Core;

class Audit {
    public static function log(
        ?int $userId,
        string $action,
        string $entity,
        ?string $entityId,
        array $detail = []
    ): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $json = null;
            if (!empty($detail)) {
                $json = json_encode(
                    $detail,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                // JSON gagal encode → jangan paksa
                if ($json === false) {
                    $json = null;
                }
            }

            Database::execute(
                'INSERT INTO audit_log 
                (tgl, user_id, action, entity, entity_id, detail, ip)
                 VALUES (NOW(),?,?,?,?,?,?)',
                'issssss',
                [$userId, $action, $entity, $entityId, $json, $ip]
            );

        } catch (\Throwable $e) {
            // ⛔ JANGAN ganggu API
            error_log('[AUDIT ERROR] '.$e->getMessage());
        }
    }
}
