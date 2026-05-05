<?php

function generateQrCodeSvg(string $payload, string $directory = __DIR__ . '/../../uploads/qr'): array
{
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $hash = hash('sha256', $payload);
    $cells = str_split(substr($hash, 0, 225));
    $size = 10;
    $dimension = 15;
    $svg = [];

    $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="260" viewBox="0 0 220 260">';
    $svg[] = '<rect width="220" height="260" fill="#ffffff"/>';
    for ($y = 0; $y < $dimension; $y++) {
        for ($x = 0; $x < $dimension; $x++) {
            $index = ($y * $dimension + $x) % count($cells);
            $value = hexdec($cells[$index]) % 2;
            $color = $value ? '#0B2A5B' : '#ffffff';
            $svg[] = '<rect x="' . (20 + ($x * $size)) . '" y="' . (20 + ($y * $size)) . '" width="' . $size . '" height="' . $size . '" fill="' . $color . '" stroke="#E5E7EB" stroke-width="0.2"/>';
        }
    }
    $svg[] = '<text x="20" y="210" font-size="12" font-family="Arial" fill="#1F2937">EduSchedule Pro QR</text>';
    $svg[] = '<text x="20" y="230" font-size="10" font-family="Arial" fill="#6B7280">' . htmlspecialchars(substr($payload, 0, 50), ENT_QUOTES) . '</text>';
    $svg[] = '</svg>';

    $filename = 'qr_' . time() . '_' . substr($hash, 0, 8) . '.svg';
    $filepath = rtrim($directory, '/') . '/' . $filename;
    file_put_contents($filepath, implode('', $svg));

    return [
        'filename' => $filename,
        'path' => $filepath,
        'url' => '/uploads/qr/' . $filename,
    ];
}
