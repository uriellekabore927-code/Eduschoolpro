<?php

final class SimplePdfDocument
{
    private array $pages = [];
    private int $currentPage = -1;
    private int $imageCounter = 0;

    public function addPage(string $orientation = 'P'): void
    {
        $isLandscape = strtoupper($orientation) === 'L';
        $this->pages[] = [
            'width' => $isLandscape ? 841.89 : 595.28,
            'height' => $isLandscape ? 595.28 : 841.89,
            'content' => '',
            'xobjects' => [],
        ];
        $this->currentPage = count($this->pages) - 1;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color = [0, 0, 0], float $width = 1): void
    {
        $this->append(sprintf(
            "q %s RG %.2F w %.2F %.2F m %.2F %.2F l S Q\n",
            $this->pdfColor($color),
            $width,
            $x1,
            $this->toPdfY($y1),
            $x2,
            $this->toPdfY($y2)
        ));
    }

    public function rect(float $x, float $y, float $w, float $h, array $stroke = [0.87, 0.90, 0.95], ?array $fill = null, float $lineWidth = 1): void
    {
        $pdfY = $this->pageHeight() - $y - $h;
        $operator = $fill ? 'B' : 'S';
        $fillSegment = $fill ? sprintf(' %s rg', $this->pdfColor($fill)) : '';

        $this->append(sprintf(
            "q %s RG%s %.2F w %.2F %.2F %.2F %.2F re %s Q\n",
            $this->pdfColor($stroke),
            $fillSegment,
            $lineWidth,
            $x,
            $pdfY,
            $w,
            $h,
            $operator
        ));
    }

    public function text(float $x, float $y, string $text, float $size = 12, string $font = 'Helvetica', array $color = [0, 0, 0]): void
    {
        $fontKey = $this->fontKey($font);
        $encoded = $this->escapeText($text);
        $this->append(sprintf(
            "BT %s rg /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $this->pdfColor($color),
            $fontKey,
            $size,
            $x,
            $this->toPdfY($y),
            $encoded
        ));
    }

    public function image(string $path, float $x, float $y, float $w, float $h): void
    {
        if (!is_file($path)) {
            $this->drawFallbackLogo($x, $y, $w, $h);
            return;
        }

        $imageData = file_get_contents($path);
        if ($imageData === false) {
            $this->drawFallbackLogo($x, $y, $w, $h);
            return;
        }

        $directImage = $this->loadDirectImage($imageData);
        if ($directImage) {
            $imageName = 'Im' . (++$this->imageCounter);
            $this->pages[$this->currentPage]['xobjects'][] = [
                'name' => $imageName,
                ...$directImage,
            ];

            $pdfY = $this->pageHeight() - $y - $h;
            $this->append(sprintf(
                "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
                $w,
                $h,
                $x,
                $pdfY,
                $imageName
            ));
            return;
        }

        if (!function_exists('imagecreatefromstring')) {
            $this->drawFallbackLogo($x, $y, $w, $h);
            return;
        }

        $image = imagecreatefromstring($imageData);
        if (!$image) {
            $this->drawFallbackLogo($x, $y, $w, $h);
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);
        $jpegData = ob_get_clean();
        if ($jpegData === false) {
            $this->drawFallbackLogo($x, $y, $w, $h);
            return;
        }

        $imageName = 'Im' . (++$this->imageCounter);
        $this->pages[$this->currentPage]['xobjects'][] = [
            'name' => $imageName,
            'width' => $width,
            'height' => $height,
            'data' => $jpegData,
            'filter' => 'DCTDecode',
            'colorSpace' => 'DeviceRGB',
            'bitsPerComponent' => 8,
        ];

        $pdfY = $this->pageHeight() - $y - $h;
        $this->append(sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $w,
            $h,
            $x,
            $pdfY,
            $imageName
        ));
    }

    private function loadDirectImage(string $imageData): ?array
    {
        if (str_starts_with($imageData, "\x89PNG\r\n\x1a\n")) {
            return $this->loadPngImage($imageData);
        }

        if (str_starts_with($imageData, "\xff\xd8")) {
            $info = @getimagesizefromstring($imageData);
            if (!$info) {
                return null;
            }

            return [
                'width' => (int) $info[0],
                'height' => (int) $info[1],
                'data' => $imageData,
                'filter' => 'DCTDecode',
                'colorSpace' => 'DeviceRGB',
                'bitsPerComponent' => 8,
            ];
        }

        return null;
    }

    private function loadPngImage(string $imageData): ?array
    {
        $offset = 8;
        $length = strlen($imageData);
        $width = 0;
        $height = 0;
        $bitDepth = 0;
        $colorType = null;
        $idat = '';

        while ($offset + 8 <= $length) {
            $chunkLength = unpack('N', substr($imageData, $offset, 4))[1] ?? 0;
            $chunkType = substr($imageData, $offset + 4, 4);
            $chunkData = substr($imageData, $offset + 8, $chunkLength);
            $offset += 12 + $chunkLength;

            if ($chunkType === 'IHDR') {
                $header = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $chunkData);
                if (!$header || (int) $header['compression'] !== 0 || (int) $header['filter'] !== 0 || (int) $header['interlace'] !== 0) {
                    return null;
                }
                $width = (int) $header['width'];
                $height = (int) $header['height'];
                $bitDepth = (int) $header['bitDepth'];
                $colorType = (int) $header['colorType'];
                continue;
            }

            if ($chunkType === 'IDAT') {
                $idat .= $chunkData;
                continue;
            }

            if ($chunkType === 'IEND') {
                break;
            }
        }

        if ($width <= 0 || $height <= 0 || $bitDepth !== 8 || $idat === '') {
            return null;
        }

        $colors = match ($colorType) {
            0 => 1,
            2 => 3,
            default => null,
        };

        if ($colors === null) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'data' => $idat,
            'filter' => 'FlateDecode',
            'colorSpace' => $colors === 1 ? 'DeviceGray' : 'DeviceRGB',
            'bitsPerComponent' => 8,
            'decodeParms' => sprintf('<< /Predictor 15 /Colors %d /BitsPerComponent 8 /Columns %d >>', $colors, $width),
        ];
    }

    private function drawFallbackLogo(float $x, float $y, float $w, float $h): void
    {
        $blue = [0.114, 0.306, 0.847];
        $white = [1.0, 1.0, 1.0];
        $gray = [0.38, 0.46, 0.65];

        $this->rect($x, $y, $w, $h, $blue, $blue);
        $this->rect($x + 4, $y + 4, $w - 8, $h - 8, $white, $white);
        $this->text($x + 10, $y + ($h / 2) - 10, 'ES', 18, 'Helvetica-Bold', $blue);
        $this->text($x + 10, $y + ($h / 2) + 8, 'EduSchedule', 8, 'Helvetica', $gray);
    }

    public function multiLineText(float $x, float $y, float $w, string $text, float $size = 12, float $lineHeight = 15, string $font = 'Helvetica', array $color = [0, 0, 0], string $align = 'L'): float
    {
        $lines = $this->wrapText($text, $w, $size, $font);
        $currentY = $y;

        foreach ($lines as $line) {
            $lineWidth = $this->estimateTextWidth($line, $size, $font);
            $lineX = $x;
            if ($align === 'C') {
                $lineX = $x + max(0, ($w - $lineWidth) / 2);
            } elseif ($align === 'R') {
                $lineX = $x + max(0, $w - $lineWidth);
            }
            $this->text($lineX, $currentY, $line, $size, $font, $color);
            $currentY += $lineHeight;
        }

        return $currentY;
    }

    public function output(string $filepath): void
    {
        $objects = [];
        $fontIds = [
            'F1' => 3,
            'F2' => 4,
            'F3' => 5,
        ];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>';

        $nextId = 6;
        $pageRefs = [];

        foreach ($this->pages as $page) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageRefs[] = $pageId . ' 0 R';
            $stream = $page['content'];

            $resources = '<< /Font << /F1 ' . $fontIds['F1'] . ' 0 R /F2 ' . $fontIds['F2'] . ' 0 R /F3 ' . $fontIds['F3'] . ' 0 R >>';
            if (!empty($page['xobjects'])) {
                $resources .= ' /XObject << ';
                foreach ($page['xobjects'] as $index => $xobject) {
                    $resources .= '/' . $xobject['name'] . ' ' . ($nextId + $index) . ' 0 R ';
                }
                $resources .= '>>';
            }
            $resources .= ' >>';

            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>',
                $page['width'],
                $page['height'],
                $resources,
                $contentId
            );

            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";

            foreach ($page['xobjects'] as $xobject) {
                $xobjectId = $nextId++;
                $decodeParms = !empty($xobject['decodeParms']) ? ' /DecodeParms ' . $xobject['decodeParms'] : '';
                $objects[$xobjectId] = sprintf(
                    "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent %d /Filter /%s%s /Length %d >>\nstream\n%sendstream",
                    $xobject['width'],
                    $xobject['height'],
                    $xobject['colorSpace'] ?? 'DeviceRGB',
                    $xobject['bitsPerComponent'] ?? 8,
                    $xobject['filter'] ?? 'DCTDecode',
                    $decodeParms,
                    strlen($xobject['data']),
                    $xobject['data']
                );
            }
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($pageRefs) . ' /Kids [' . implode(' ', $pageRefs) . '] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $content) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $content . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= max(array_keys($objects)); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        file_put_contents($filepath, $pdf);
    }

    private function append(string $content): void
    {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
        $this->pages[$this->currentPage]['content'] .= $content;
    }

    public function pageWidth(): float
    {
        return (float) ($this->pages[$this->currentPage]['width'] ?? 0);
    }

    private function pageHeight(): float
    {
        return (float) ($this->pages[$this->currentPage]['height'] ?? 0);
    }

    private function toPdfY(float $y): float
    {
        return $this->pageHeight() - $y;
    }

    private function fontKey(string $font): string
    {
        return match ($font) {
            'Helvetica-Bold' => 'F2',
            'Helvetica-Oblique' => 'F3',
            default => 'F1',
        };
    }

    private function pdfColor(array $color): string
    {
        return implode(' ', array_map(static fn ($value) => number_format((float) $value, 3, '.', ''), $color));
    }

    private function escapeText(string $text): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
        $converted = str_replace(["\r", "\n", "\t"], ' ', $converted);
        return addcslashes($converted, "\\()");
    }

    private function wrapText(string $text, float $width, float $size, string $font): array
    {
        $paragraphs = preg_split("/\r\n|\r|\n/", trim($text)) ?: [''];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $words = preg_split('/\s+/', $paragraph) ?: [];
            $current = '';

            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if ($this->estimateTextWidth($candidate, $size, $font) <= $width) {
                    $current = $candidate;
                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                }

                if ($this->estimateTextWidth($word, $size, $font) <= $width) {
                    $current = $word;
                    continue;
                }

                $parts = str_split($word);
                $chunk = '';
                foreach ($parts as $part) {
                    $probe = $chunk . $part;
                    if ($this->estimateTextWidth($probe, $size, $font) <= $width) {
                        $chunk = $probe;
                    } else {
                        if ($chunk !== '') {
                            $lines[] = $chunk;
                        }
                        $chunk = $part;
                    }
                }
                $current = $chunk;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines ?: [''];
    }

    private function estimateTextWidth(string $text, float $size, string $font): float
    {
        $ratio = match ($font) {
            'Helvetica-Bold' => 0.57,
            'Helvetica-Oblique' => 0.54,
            default => 0.52,
        };
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        return $length * $size * $ratio;
    }
}

function ensurePdfDirectory(): string
{
    $directory = __DIR__ . '/../../uploads/pdf';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    return $directory;
}

function exportPdfPayload(string $filename, string $filepath): array
{
    return [
        'filename' => $filename,
        'path' => $filepath,
        'url' => '/uploads/pdf/' . $filename,
    ];
}

function pdfCommonHeader(SimplePdfDocument $pdf, string $title, string $subtitle, string $detail = ''): void
{
    $navy  = [0.043, 0.165, 0.357];
    $blue  = [0.114, 0.306, 0.847];
    $white = [1.0, 1.0, 1.0];
    $light = [0.88, 0.92, 0.98];

    $pdf->rect(0, 0, $pdf->pageWidth(), 80, $navy, $navy);
    $pdf->rect(18, 14, 58, 58, $white, $white);
    $pdf->image(__DIR__ . '/../../assets/img/logo.png', 22, 18, 50, 50);

    $pdf->text(86, 28, 'EduSchedule Pro', 16, 'Helvetica-Bold', $white);
    $pdf->text(86, 46, $subtitle, 9, 'Helvetica', [0.82, 0.87, 0.94]);
    if ($detail !== '') {
        $pdf->text(86, 58, $detail, 8.5, 'Helvetica-Oblique', [0.75, 0.82, 0.91]);
    }

    $pdf->text($pdf->pageWidth() - 260, 30, $title, 20, 'Helvetica-Bold', $white);
    $pdf->text($pdf->pageWidth() - 260, 52, 'Document officiel', 9, 'Helvetica', [0.82, 0.87, 0.94]);
    $pdf->rect(0, 80, $pdf->pageWidth(), 4, $blue, $blue);
    $pdf->rect(18, 91, $pdf->pageWidth() - 36, 1, $light, $light);
}

function pdfVacationContinuationHeader(SimplePdfDocument $pdf, string $ref): void
{
    $navy  = [0.043, 0.165, 0.357];
    $blue  = [0.114, 0.306, 0.847];
    $white = [1.0, 1.0, 1.0];
    $gray  = [0.68, 0.76, 0.90];
    $pdf->rect(0, 0, $pdf->pageWidth(), 62, $navy, $navy);
    $pdf->rect(18, 12, 58, 58, $white, $white);
    $pdf->image(__DIR__ . '/../../assets/img/logo.png', 22, 16, 50, 50);
    $pdf->text(86, 28, 'FICHE DE VACATION', 16, 'Helvetica-Bold', $white);
    $pdf->text(86, 46, 'Continuation du document', 9, 'Helvetica', $gray);
    $pdf->text($pdf->pageWidth() - 190, 28, 'Ref. ' . $ref, 10, 'Helvetica-Bold', $white);
    $pdf->rect(0, 62, $pdf->pageWidth(), 4, $blue, $blue);
}

function generateVacationPdf(array $vacation): array
{
    $directory = ensurePdfDirectory();
    $filename  = 'vacation_' . $vacation['id'] . '.pdf';
    $filepath  = $directory . '/' . $filename;

    // Palette
    $navy     = [0.043, 0.165, 0.357];
    $blue     = [0.114, 0.306, 0.847];
    $white    = [1.0,   1.0,   1.0  ];
    $dark     = [0.08,  0.10,  0.14 ];
    $muted    = [0.42,  0.48,  0.56 ];
    $lightBg  = [0.94,  0.96,  0.99 ];
    $cardBg   = [0.98,  0.99,  1.00 ];
    $border   = [0.80,  0.86,  0.93 ];
    $green    = [0.06,  0.47,  0.22 ];
    $greenBg  = [0.88,  0.97,  0.90 ];
    $rowAlt   = [0.96,  0.97,  0.99 ];

    // Reference + labels
    $ref = 'VAC-' . substr((string)($vacation['annee'] ?? '2026'), -2)
         . '-' . str_pad((string)($vacation['id'] ?? 0), 3, '0', STR_PAD_LEFT);
    $monthNames = ['', 'Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin',
                   'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Decembre'];
    $periodLabel  = ($monthNames[(int)($vacation['mois'] ?? 1)] ?? '-') . ' ' . ($vacation['annee'] ?? '');
    $statusLabels = ['generee' => 'En attente controle', 'controlee' => 'Controlee',
                     'validee' => 'Validee - comptable', 'payee' => 'Payee'];
    $statusLabel  = $statusLabels[$vacation['statut'] ?? ''] ?? ucfirst((string)($vacation['statut'] ?? '-'));

    $pdf = new SimplePdfDocument();
    $pdf->addPage('P');
    pdfCommonHeader($pdf, 'FICHE DE VACATION', 'Eduschoolpro', 'Ref. ' . $ref);

    // ── REFERENCE BAR ────────────────────────────────────────────────────────
    $pdf->rect(22, 87, 551, 22, $border, $lightBg);
    $pdf->text(30, 101,  'Ref. : ' . $ref,               9.5, 'Helvetica-Bold', $dark);
    $pdf->text(210, 101, 'Periode : ' . $periodLabel,     9.5, 'Helvetica',      $dark);
    $pdf->text(430, 101, 'Statut : ' . $statusLabel,      9.5, 'Helvetica-Bold', $dark);

    // ── TWO INFO CARDS ────────────────────────────────────────────────────────
    $cardY   = 120;
    $cardH   = 110;
    $cardHdr = 20;
    $lx = 22; $lw = 262;
    $rx = 311; $rw = 262;

    // Left card — Enseignant
    $pdf->rect($lx, $cardY, $lw, $cardHdr, $navy, $navy);
    $pdf->text($lx + 10, $cardY + 14, 'INFORMATIONS ENSEIGNANT', 8, 'Helvetica-Bold', $white);
    $pdf->rect($lx, $cardY + $cardHdr, $lw, $cardH, $border, $cardBg);

    $infoRows = [
        ['Enseignant', $vacation['enseignant'] ?? '-'],
        ['Classes',    $vacation['classes']    ?? '-'],
        ['Matieres',   $vacation['matieres']   ?? '-'],
        ['Periode',    $periodLabel],
    ];
    $ey = $cardY + $cardHdr + 16;
    foreach ($infoRows as [$lbl, $val]) {
        $pdf->text($lx + 8,  $ey, $lbl . ' :',          8.5, 'Helvetica-Bold', $muted);
        $pdf->multiLineText($lx + 86, $ey, $lw - 96, (string)$val, 8.5, 10, 'Helvetica', $dark);
        $ey += 20;
    }

    // Right card — Financier
    $pdf->rect($rx, $cardY, $rw, $cardHdr, $navy, $navy);
    $pdf->text($rx + 10, $cardY + 14, 'RECAPITULATIF FINANCIER', 8, 'Helvetica-Bold', $white);
    $pdf->rect($rx, $cardY + $cardHdr, $rw, $cardH, $border, $cardBg);

    $finRows = [
        ['Total heures',    (string)($vacation['total_heures'] ?? 0) . 'h00', false],
        ['Montant brut',    number_format((float)($vacation['montant_brut'] ?? 0), 0, ',', ' ') . ' FCFA', false],
        ['Retenues (10%)',  number_format((float)($vacation['retenues']    ?? 0), 0, ',', ' ') . ' FCFA', false],
    ];
    $fy = $cardY + $cardHdr + 16;
    foreach ($finRows as [$lbl, $val]) {
        $pdf->text($rx + 8,  $fy, $lbl . ' :',     8.5, 'Helvetica-Bold', $muted);
        $pdf->text($rx + 140, $fy, (string)$val,    8.5, 'Helvetica',      $dark);
        $fy += 20;
    }
    // NET highlighted row
    $pdf->rect($rx + 6, $fy - 4, $rw - 12, 22, $border, $greenBg);
    $pdf->text($rx + 12, $fy + 12, 'NET A PAYER :',     10, 'Helvetica-Bold', $green);
    $pdf->text($rx + 140, $fy + 12,
        number_format((float)($vacation['montant_net'] ?? 0), 0, ',', ' ') . ' FCFA',
        10, 'Helvetica-Bold', $green);

    // ── SESSIONS TABLE ────────────────────────────────────────────────────────
    $tableY = $cardY + $cardHdr + $cardH + 28;

    // Section title with underline accent
    $pdf->text(22, $tableY - 8, 'RECAPITULATIF DES SEANCES', 9.5, 'Helvetica-Bold', $dark);
    $pdf->line(22, $tableY - 6, 190, $tableY - 6, $blue, 1.5);

    $columns = [
        ['Date',    86],
        ['Horaire', 88],
        ['Classe',  100],
        ['Matiere', 196],
        ['Heures',  81],
    ];
    $startX  = 22;
    $headerH = 22;
    $rowH    = 20;
    $maxY    = 740;
    $curY    = $tableY;

    $drawTblHeader = static function(SimplePdfDocument $pdf, float $x, float $y, array $cols, float $h) use ($navy, $white): void {
        $cx = $x;
        foreach ($cols as [$lbl, $w]) {
            $pdf->rect($cx, $y, $w, $h, $navy, $navy);
            $pdf->text($cx + 7, $y + 15, $lbl, 8.5, 'Helvetica-Bold', $white);
            $cx += $w;
        }
    };

    $drawTblHeader($pdf, $startX, $curY, $columns, $headerH);
    $curY += $headerH;

    $lignes = $vacation['lignes'] ?? [];
    if (empty($lignes)) {
        $totalW = array_sum(array_column($columns, 1));
        $pdf->rect($startX, $curY, $totalW, 24, $border);
        $pdf->text($startX + 12, $curY + 16, 'Aucune seance enregistree pour cette vacation.', 9, 'Helvetica-Oblique', $muted);
        $curY += 24;
    } else {
        foreach ($lignes as $i => $line) {
            if ($curY + $rowH > $maxY) {
                $pdf->addPage('P');
                pdfVacationContinuationHeader($pdf, $ref);
                $curY = 74;
                $drawTblHeader($pdf, $startX, $curY, $columns, $headerH);
                $curY += $headerH;
            }
            $rowFill = ($i % 2 === 0) ? $white : $rowAlt;
            $cx = $startX;
            $values = [
                formatVacationLinePdfDate($line),
                substr((string)($line['heure_debut'] ?? ''), 0, 5) . ' - ' . substr((string)($line['heure_fin'] ?? ''), 0, 5),
                $line['classe']       ?? '-',
                $line['matiere']      ?? '-',
                str_replace('.00', '', (string)($line['duree_heures'] ?? '0')) . 'h00',
            ];
            foreach ($columns as $idx => [$lbl, $w]) {
                $pdf->rect($cx, $curY, $w, $rowH, $border, $rowFill);
                $pdf->multiLineText($cx + 6, $curY + 14, $w - 10, $values[$idx], 8, 10, 'Helvetica', $dark);
                $cx += $w;
            }
            $curY += $rowH;
        }
        // Total row
        $cx = $startX;
        $totals = ['TOTAL', '', '', (string)(count($lignes)) . ' seance(s)', (string)($vacation['total_heures'] ?? 0) . 'h00'];
        foreach ($columns as $idx => [$lbl, $w]) {
            $pdf->rect($cx, $curY, $w, $rowH, $navy, [0.89, 0.93, 0.97]);
            $pdf->text($cx + 6, $curY + 14, $totals[$idx], 8.5, 'Helvetica-Bold', $dark);
            $cx += $w;
        }
        $curY += $rowH;
    }

    // ── CIRCUIT DE VALIDATION ─────────────────────────────────────────────────
    $sigY = max($curY + 24, 600);
    if ($sigY + 100 > 810) {
        $pdf->addPage('P');
        pdfVacationContinuationHeader($pdf, $ref);
        $sigY = 74;
    }

    $pdf->text(22, $sigY - 8, 'CIRCUIT DE VALIDATION', 9.5, 'Helvetica-Bold', $dark);
    $pdf->line(22, $sigY - 6, 182, $sigY - 6, $blue, 1.5);

    $sigBoxW = 258;
    $sigBoxH = 82;
    $sigSlots = [
        ['Surveillant de classe', 'surveillant', 22],
        ['Responsable comptable', 'comptable',   315],
    ];
    foreach ($sigSlots as [$title, $roleKey, $sx]) {
        // Box
        $pdf->rect($sx, $sigY, $sigBoxW, $sigBoxH, $border);
        // Header
        $pdf->rect($sx, $sigY, $sigBoxW, 20, $navy, $navy);
        $pdf->text($sx + 10, $sigY + 14, strtoupper($title), 8, 'Helvetica-Bold', $white);

        // Find matching validation
        $v = null;
        foreach (($vacation['validations'] ?? []) as $val) {
            if (strtolower((string)($val['role_validateur'] ?? '')) === $roleKey) {
                $v = $val;
                break;
            }
        }

        if ($v) {
            $pdf->rect($sx + 4, $sigY + 24, $sigBoxW - 8, $sigBoxH - 28, $border, $greenBg);
            $pdf->text($sx + 12, $sigY + 38, 'Valide par :', 8.5, 'Helvetica-Bold', $muted);
            $pdf->text($sx + 80, $sigY + 38, (string)($v['validateur_nom'] ?? '-'), 8.5, 'Helvetica-Bold', $dark);
            $pdf->text($sx + 12, $sigY + 54, 'Le :',         8.5, 'Helvetica-Bold', $muted);
            $pdf->text($sx + 80, $sigY + 54, substr((string)($v['date_validation'] ?? ''), 0, 10), 8.5, 'Helvetica', $dark);
            $pdf->text($sx + 12, $sigY + 70, 'VISA CONFORME', 9, 'Helvetica-Bold', $green);
        } else {
            $pdf->text($sx + 12, $sigY + 38, 'Signature :',  8.5, 'Helvetica-Bold', $muted);
            $pdf->line($sx + 76, $sigY + 37, $sx + $sigBoxW - 12, $sigY + 37, $border, 0.5);
            $pdf->text($sx + 12, $sigY + 56, 'Date :',       8.5, 'Helvetica-Bold', $muted);
            $pdf->line($sx + 52, $sigY + 55, $sx + $sigBoxW - 12, $sigY + 55, $border, 0.5);
            $pdf->text($sx + 12, $sigY + 73, 'En attente de validation', 8, 'Helvetica-Oblique', $muted);
        }
    }

    // ── FOOTER ────────────────────────────────────────────────────────────────
    $footerY = 812;
    $pdf->rect(0, $footerY - 2, 595.28, 1, $blue, $blue);
    $pdf->text(22,  $footerY + 12, 'Eduschoolpro', 7.5, 'Helvetica', $muted);
    $pdf->text(22,  $footerY + 24, 'Document genere le ' . date('d/m/Y a H:i'), 7, 'Helvetica-Oblique', $muted);
    $pdf->text(533, $footerY + 12, 'Page 1', 7.5, 'Helvetica', $muted);

    $pdf->output($filepath);
    return exportPdfPayload($filename, $filepath);
}

function generateTimetablePdfExport(array $emplois, string $weekStart, string $scope = 'single', ?string $classLabel = null): array
{
    $directory = ensurePdfDirectory();
    $filename = 'emploi_temps_' . $scope . '_' . date('Ymd_His') . '.pdf';
    $filepath = $directory . '/' . $filename;

    $pdf = new SimplePdfDocument();
    if (!$emplois) {
        renderEmptyTimetablePdfPage($pdf, $weekStart);
    } else {
        foreach ($emplois as $emploi) {
            renderTimetablePdfPage($pdf, $emploi, $weekStart, $scope, $classLabel);
        }
    }

    $pdf->output($filepath);
    return exportPdfPayload($filename, $filepath);
}

function generateVacationsReportPdf(array $vacations, string $periodLabel): array
{
    $directory = ensurePdfDirectory();
    $filename = 'rapport_vacations_' . date('Ymd_His') . '.pdf';
    $filepath = $directory . '/' . $filename;

    $pdf = new SimplePdfDocument();
    $pdf->addPage('L');
    pdfCommonHeader($pdf, 'Rapport des vacations', 'État des vacations', $periodLabel ?: 'Période courante');
    $pdf->text(44, 110, 'Synthèse détaillée des vacations', 12, 'Helvetica', [0.392, 0.455, 0.541]);

    $columns = [
        ['Réf.', 90],
        ['Enseignant', 170],
        ['Classe(s)', 150],
        ['Période', 90],
        ['Heures', 70],
        ['Net', 95],
        ['Statut', 85],
    ];

    $drawHeader = static function (SimplePdfDocument $pdf, float $x, float $y, array $columns): void {
        $cursor = $x;
        foreach ($columns as [$label, $width]) {
            $pdf->rect($cursor, $y, $width, 24, [0.82, 0.86, 0.92], [0.97, 0.98, 0.99]);
            $pdf->text($cursor + 6, $y + 15, $label, 9.5, 'Helvetica-Bold');
            $cursor += $width;
        }
    };

    $startX = 36;
    $currentY = 138;
    $drawHeader($pdf, $startX, $currentY, $columns);
    $currentY += 24;

    if (!$vacations) {
        $pdf->rect($startX, $currentY, 750, 28, [0.88, 0.91, 0.95]);
        $pdf->text($startX + 12, $currentY + 18, 'Aucune vacation disponible pour le moment.', 10.5);
    } else {
        foreach ($vacations as $item) {
            if ($currentY + 22 > 540) {
                $pdf->addPage('L');
                pdfCommonHeader($pdf, 'Rapport des vacations', 'État des vacations', $periodLabel ?: 'Période courante');
                $pdf->text(44, 110, 'Synthèse détaillée des vacations', 12, 'Helvetica', [0.392, 0.455, 0.541]);
                $currentY = 138;
                $drawHeader($pdf, $startX, $currentY, $columns);
                $currentY += 24;
            }

            $values = [
                'VAC-' . substr((string) ($item['annee'] ?? ''), -2) . '-' . str_pad((string) ($item['id'] ?? 0), 3, '0', STR_PAD_LEFT),
                $item['enseignant'] ?? '-',
                $item['classes'] ?? '-',
                formatVacationReportPeriod($item),
                (string) ($item['total_heures'] ?? 0) . 'h00',
                number_format((float) ($item['montant_net'] ?? 0), 0, ',', ' ') . ' FCFA',
                ucfirst((string) ($item['statut'] ?? '-')),
            ];

            $cursor = $startX;
            foreach ($columns as $index => [$label, $width]) {
                $pdf->rect($cursor, $currentY, $width, 22, [0.88, 0.91, 0.95]);
                $pdf->multiLineText($cursor + 5, $currentY + 14, $width - 10, $values[$index], 8.8, 10);
                $cursor += $width;
            }
            $currentY += 22;
        }
    }

    $pdf->output($filepath);
    return exportPdfPayload($filename, $filepath);
}

function generateReferentialsReportPdf(array $datasets): array
{
    $directory = ensurePdfDirectory();
    $filename = 'rapport_referentiels_' . date('Ymd_His') . '.pdf';
    $filepath = $directory . '/' . $filename;

    $pdf = new SimplePdfDocument();
    $pdf->addPage('P');
    pdfCommonHeader($pdf, 'Rapport des référentiels', 'Synthèse des données de paramétrage');
    $pdf->text(44, 110, 'Récapitulatif des ressources paramétrées', 11, 'Helvetica', [0.392, 0.455, 0.541]);

    $sections = [
        'Classes' => $datasets['classes'] ?? [],
        'Matières' => $datasets['matieres'] ?? [],
        'Enseignants' => $datasets['enseignants'] ?? [],
        'Salles' => $datasets['salles'] ?? [],
    ];

    $y = 140;
    foreach ($sections as $title => $rows) {
        if ($y > 720) {
            $pdf->addPage('P');
            pdfCommonHeader($pdf, 'Rapport des référentiels', 'Synthèse des données de paramétrage');
            $y = 140;
        }

        $pdf->text(44, $y, $title, 14, 'Helvetica-Bold');
        $y += 20;

        if (!$rows) {
            $pdf->text(56, $y, 'Aucune donnée disponible.', 10.5, 'Helvetica', [0.392, 0.455, 0.541]);
            $y += 24;
            continue;
        }

        foreach ($rows as $row) {
            if ($y > 760) {
                $pdf->addPage('P');
                pdfCommonHeader($pdf, 'Rapport des référentiels', 'Synthèse des données de paramétrage');
                $y = 140;
            }
            $line = implode(' | ', array_filter(array_map(static function ($value) {
                return trim((string) $value);
            }, array_values($row))));
            $y = $pdf->multiLineText(56, $y, 485, $line, 9.5, 12) + 8;
        }
        $y += 10;
    }

    $pdf->output($filepath);
    return exportPdfPayload($filename, $filepath);
}

function generateReferentialsExcelExport(array $datasets): array
{
    $directory = ensurePdfDirectory();
    $filename = 'rapport_referentiels_' . date('Ymd_His') . '.csv';
    $filepath = $directory . '/' . $filename;

    $handle = fopen($filepath, 'wb');
    fwrite($handle, "\xEF\xBB\xBF");

    foreach ([
        'Classes' => $datasets['classes'] ?? [],
        'Matières' => $datasets['matieres'] ?? [],
        'Enseignants' => $datasets['enseignants'] ?? [],
        'Salles' => $datasets['salles'] ?? [],
    ] as $section => $rows) {
        fputcsv($handle, [$section], ';');
        if (!$rows) {
            fputcsv($handle, ['Aucune donnée disponible'], ';');
            fputcsv($handle, [], ';');
            continue;
        }
        fputcsv($handle, array_keys($rows[0]), ';');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), ';');
        }
        fputcsv($handle, [], ';');
    }

    fclose($handle);
    return exportPdfPayload($filename, $filepath);
}

function renderTimetablePdfPage(SimplePdfDocument $pdf, array $emploi, string $weekStart, string $scope, ?string $classLabel): void
{
    $pdf->addPage('L');
    $title = $scope === 'all' ? 'EMPLOIS DU TEMPS' : 'EMPLOI DU TEMPS';
    $subtitle = 'Semaine du ' . formatWeekRangeLabel($weekStart);
    pdfCommonHeader($pdf, $title, $subtitle, (string) ($emploi['classe_code'] ?? $classLabel ?? ''));

    $heading = $scope === 'all'
        ? 'EMPLOIS DU TEMPS DU ' . formatWeekRangeLabel($weekStart)
        : 'EMPLOI DU TEMPS DU ' . formatWeekRangeLabel($weekStart);
    $pdf->text(220, 120, $heading, 14, 'Helvetica-Bold');
    $pdf->text(720, 120, (string) ($emploi['classe_code'] ?? $classLabel ?? ''), 20, 'Helvetica-Bold', [0.114, 0.306, 0.847]);

    $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $dayLabels = [];
    foreach ($days as $index => $day) {
        $dayLabels[] = formatPrintableDayLabel($weekStart, $day, $index);
    }

    $slots = [
        ['label' => "07h30\na\n09h30", 'start' => '07:30:00', 'end' => '09:30:00'],
        ['label' => "10h00\na\n12h15", 'start' => '10:00:00', 'end' => '12:15:00'],
        ['label' => "14h00\na\n17h00", 'start' => '14:00:00', 'end' => '17:00:00'],
    ];

    $tableX = 36;
    $tableY = 120;
    $timeWidth = 72;
    $dayWidth = 118;
    $headerHeight = 26;
    $slotHeight = 112;
    $tableWidth = $timeWidth + (count($days) * $dayWidth);
    $tableHeight = $headerHeight + (count($slots) * $slotHeight);

    $pdf->rect($tableX, $tableY, $tableWidth, $tableHeight, [0.26, 0.28, 0.31]);
    $pdf->rect($tableX, $tableY, $timeWidth, $headerHeight, [0.26, 0.28, 0.31], [0.97, 0.97, 0.97]);
    $pdf->text($tableX + 10, $tableY + 17, 'Horaire', 10, 'Helvetica-Bold');

    $cursorX = $tableX + $timeWidth;
    foreach ($dayLabels as $label) {
        $pdf->rect($cursorX, $tableY, $dayWidth, $headerHeight, [0.26, 0.28, 0.31], [0.97, 0.97, 0.97]);
        $pdf->multiLineText($cursorX + 4, $tableY + 17, $dayWidth - 8, $label, 10, 12, 'Helvetica-Bold', [0, 0, 0], 'C');
        $cursorX += $dayWidth;
    }

    foreach ($slots as $slotIndex => $slot) {
        $rowY = $tableY + $headerHeight + ($slotIndex * $slotHeight);
        $pdf->rect($tableX, $rowY, $timeWidth, $slotHeight, [0.26, 0.28, 0.31]);
        $pdf->multiLineText($tableX + 14, $rowY + 30, $timeWidth - 28, $slot['label'], 10, 16, 'Helvetica-Bold', [0.11, 0.16, 0.23], 'C');

        foreach ($days as $dayIndex => $day) {
            $cellX = $tableX + $timeWidth + ($dayIndex * $dayWidth);
            $pdf->rect($cellX, $rowY, $dayWidth, $slotHeight, [0.86, 0.89, 0.94]);

            $match = null;
            foreach (($emploi['creneaux'] ?? []) as $creneau) {
                if (($creneau['jour'] ?? '') === $day && matchPrintableSlot((string) ($creneau['heure_debut'] ?? ''), (string) ($creneau['heure_fin'] ?? ''), $slot['start'], $slot['end'])) {
                    $match = $creneau;
                    break;
                }
            }

            if ($match) {
                $innerY = $rowY + 18;
                if (($match['heure_debut'] ?? '') !== $slot['start'] || ($match['heure_fin'] ?? '') !== $slot['end']) {
                    $innerY = $pdf->multiLineText($cellX + 10, $innerY, $dayWidth - 20, '[' . substr((string) $match['heure_debut'], 0, 5) . ' : ' . substr((string) $match['heure_fin'], 0, 5) . ']', 9, 11, 'Helvetica-Bold', [0.06, 0.09, 0.14], 'C') + 4;
                }
                $innerY = $pdf->multiLineText($cellX + 10, $innerY, $dayWidth - 20, (string) ($match['matiere_libelle'] ?? ''), 10.5, 13, 'Helvetica-Bold', [0.06, 0.09, 0.14], 'C') + 8;
                $pdf->multiLineText($cellX + 10, $innerY, $dayWidth - 20, (string) ($match['enseignant_nom'] ?? ''), 9.5, 12, 'Helvetica-Oblique', [0.20, 0.23, 0.27], 'C');
            }

            $pdf->rect($cellX, $rowY + $slotHeight - 18, $dayWidth, 18, [0.90, 0.92, 0.96], [0.95, 0.96, 0.98]);
            $pdf->multiLineText($cellX + 6, $rowY + $slotHeight - 5, $dayWidth - 12, (string) ($match['salle_libelle'] ?? ''), 8.5, 10, 'Helvetica', [0.29, 0.35, 0.41], 'C');
        }
    }

    $footerY = $tableY + $tableHeight + 12;
    $afternoon = array_values(array_filter(($emploi['creneaux'] ?? []), static fn ($item) => !empty($item['devoir_prevu'])));
    for ($i = 0; $i < 2; $i++) {
        $footerX = $i === 0 ? 36 : 352;
        $item = $afternoon[$i] ?? null;
        $pdf->rect($footerX, $footerY, 256, 18, [0.26, 0.28, 0.31], [0.93, 0.93, 0.93]);
        $pdf->rect($footerX, $footerY + 18, 256, 18, [0.26, 0.28, 0.31]);
        $pdf->line($footerX + 152, $footerY, $footerX + 152, $footerY + 36, [0.26, 0.28, 0.31], 1);
        $pdf->text($footerX + 42, $footerY + 13, 'Devoir prevu', 9.5, 'Helvetica-Bold');
        $pdf->text($footerX + 188, $footerY + 13, 'Date', 9.5, 'Helvetica-Bold');
        $pdf->multiLineText($footerX + 8, $footerY + 31, 136, (string) ($item['devoir_prevu'] ?? ''), 8.5, 10);
        $pdf->multiLineText($footerX + 160, $footerY + 31, 88, $item ? formatPrintableFooterDate($weekStart, (string) ($item['jour'] ?? ''), (string) ($item['devoir_date'] ?? '')) : '', 8.5, 10, 'Helvetica-Oblique', [0.20, 0.23, 0.27], 'C');
    }

    $pdf->text(390, 555, 'Eduschoolpro', 11, 'Helvetica', [0.11, 0.16, 0.23]);
}

function renderEmptyTimetablePdfPage(SimplePdfDocument $pdf, string $weekStart): void
{
    $pdf->addPage('L');
    pdfCommonHeader($pdf, 'EMPLOI DU TEMPS', 'Aucune séance enregistrée', 'Semaine du ' . formatWeekRangeLabel($weekStart));
    $pdf->text(220, 180, 'Aucune séance enregistrée pour cette semaine.', 16, 'Helvetica-Bold', [0.392, 0.455, 0.541]);
}

function matchPrintableSlot(string $timeStart, string $timeEnd, string $start, string $end): bool
{
    return $timeStart < $end && $timeEnd > $start;
}

function formatPrintableDayLabel(string $weekStart, string $day, int $offset): string
{
    $date = new DateTime($weekStart);
    $date->modify('+' . $offset . ' day');
    $daysMap = ['lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi', 'jeudi' => 'Jeudi', 'vendredi' => 'Vendredi', 'samedi' => 'Samedi'];
    return ($daysMap[$day] ?? ucfirst($day)) . ' ' . $date->format('d');
}

function formatWeekRangeLabel(string $weekStart): string
{
    $start = new DateTime($weekStart);
    $end = (clone $start)->modify('+5 day');
    return $start->format('d/m/Y') . ' AU ' . $end->format('d/m/Y');
}

function formatPrintableFooterDate(string $weekStart, string $jour, string $explicitDate = ''): string
{
    if ($explicitDate !== '') {
        $date = new DateTime($explicitDate);
        return $date->format('d/m/Y');
    }
    $offsets = ['lundi' => 0, 'mardi' => 1, 'mercredi' => 2, 'jeudi' => 3, 'vendredi' => 4, 'samedi' => 5];
    $date = new DateTime($weekStart);
    $date->modify('+' . ($offsets[$jour] ?? 0) . ' day');
    return $date->format('d/m/Y');
}

function formatVacationLinePdfDate(array $line): string
{
    $offsets = ['lundi' => 0, 'mardi' => 1, 'mercredi' => 2, 'jeudi' => 3, 'vendredi' => 4, 'samedi' => 5];
    if (empty($line['semaine_debut'])) {
        return ucfirst((string) ($line['jour'] ?? ''));
    }
    $date = new DateTime((string) $line['semaine_debut']);
    $date->modify('+' . ($offsets[$line['jour']] ?? 0) . ' day');
    return $date->format('d/m/Y');
}

function formatVacationReportPeriod(array $item): string
{
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $month = $months[max(0, (int) ($item['mois'] ?? 1) - 1)] ?? (string) ($item['mois'] ?? '-');
    return $month . ' ' . ($item['annee'] ?? '-');
}
