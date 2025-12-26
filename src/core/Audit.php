<?php
namespace Core;

class Audit {
    public static function log(?int $userId, string $action, string $entity, ?string $entityId, array $detail = []): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $json = $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
        Database::execute(
            'INSERT INTO audit_log (tgl, user_id, action, entity, entity_id, detail, ip) VALUES (NOW(),?,?,?,?,?,?)',
            'issssss',
            [ $userId, $action, $entity, $entityId, $json, $ip ]
        );
    }
}
