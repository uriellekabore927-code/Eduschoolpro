<?php

require_once __DIR__ . '/../config/database.php';

function activityLogIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function logActivity(?int $userId, string $action, array|string|null $details = null, ?string $ip = null): void
{
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            INSERT INTO logs_activite (id_utilisateur, action, details, ip)
            VALUES (:id_utilisateur, :action, :details, :ip)
        ');
        $payload = is_array($details)
            ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) ($details ?? '');

        $stmt->execute([
            'id_utilisateur' => $userId,
            'action' => $action,
            'details' => $payload ?: null,
            'ip' => $ip ?: activityLogIp(),
        ]);
    } catch (Throwable $exception) {
        // Le journal ne doit jamais bloquer le flux métier.
    }
}
