<?php

function jsonResponse(bool $success, string $message, mixed $data = null, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Le corps JSON est invalide.', null, 400);
    }

    return is_array($decoded) ? $decoded : [];
}

function paginateParams(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    return [$page, $perPage, $offset];
}
