<?php

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwtSecret(): string
{
    return getenv('JWT_SECRET') ?: 'eduschedule-secret-change-me';
}

function jwtTtl(): int
{
    return (int) (getenv('JWT_TTL') ?: 86400);
}

function generateJwt(array $payload): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $issuedAt = time();
    $payload['iat'] = $issuedAt;
    $payload['exp'] = $issuedAt + jwtTtl();

    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", jwtSecret(), true);

    return "{$headerEncoded}.{$payloadEncoded}." . base64UrlEncode($signature);
}

function decodeJwt(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
    $expectedSignature = base64UrlEncode(hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", jwtSecret(), true));

    if (!hash_equals($expectedSignature, $signatureEncoded)) {
        return null;
    }

    $payload = json_decode(base64UrlDecode($payloadEncoded), true);
    if (!is_array($payload)) {
        return null;
    }

    if (($payload['exp'] ?? 0) < time()) {
        return null;
    }

    return $payload;
}

function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function requireAuth(array $roles = []): array
{
    $token = getBearerToken();
    if (!$token) {
        jsonResponse(false, 'Jeton d’authentification manquant.', null, 401);
    }

    $payload = decodeJwt($token);
    if (!$payload) {
        jsonResponse(false, 'Jeton invalide ou expiré.', null, 401);
    }

    if ($roles && !in_array($payload['role'] ?? '', $roles, true)) {
        jsonResponse(false, 'Accès non autorisé pour ce rôle.', null, 403);
    }

    return $payload;
}

function tokenPermissions(array $payload): array
{
    $permissions = $payload['permissions'] ?? [];
    return is_array($permissions) ? array_values($permissions) : [];
}

function tokenHasPermission(array $payload, string $permission): bool
{
    if (!$permission) {
        return true;
    }

    if (($payload['role'] ?? '') === 'administrateur') {
        return true;
    }

    return in_array($permission, tokenPermissions($payload), true);
}

function requirePermission(string $permission): array
{
    $payload = requireAuth();
    if (!tokenHasPermission($payload, $permission)) {
        jsonResponse(false, 'Accès non autorisé pour cette fonctionnalité.', null, 403);
    }

    return $payload;
}

function requireAnyPermission(array $permissions): array
{
    $payload = requireAuth();
    foreach ($permissions as $permission) {
        if (tokenHasPermission($payload, (string) $permission)) {
            return $payload;
        }
    }

    jsonResponse(false, 'Accès non autorisé pour cette fonctionnalité.', null, 403);
}

function requireRole(array $roles): array
{
    $payload = requireAuth();
    if (!in_array($payload['role'] ?? '', $roles, true)) {
        jsonResponse(false, 'Accès non autorisé pour ce rôle.', null, 403);
    }

    return $payload;
}

function authenticatedUserProfile(array $payload): array
{
    require_once __DIR__ . '/../models/Utilisateur.php';

    static $cache = [];
    $userId = (int) ($payload['sub'] ?? 0);
    if (!$userId) {
        return $payload;
    }

    if (!array_key_exists($userId, $cache)) {
        $model = new Utilisateur();
        $cache[$userId] = $model->findById($userId) ?: [];
    }

    return array_merge($payload, $cache[$userId]);
}

function linkedAccessId(array $user, string $type): ?int
{
    if (($user['type_lien'] ?? '') !== $type || empty($user['id_lien'])) {
        return null;
    }

    return (int) $user['id_lien'];
}
