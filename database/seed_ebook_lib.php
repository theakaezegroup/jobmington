<?php
/**
 * Helpers for seed_content.php — generate an ebook cover (GD) and PDF (self-contained).
 * The bundled libs/fpdf is an empty stub, so this ships its own minimal PDF writer.
 */

if (!defined('JOBMINGTON')) { exit; }

/** Sanitise UTF-8 to the Latin-1 range PDF base fonts use. */
function sc_pdf_text(string $s): string {
    $s = str_replace(
        ["\xE2\x80\x99", "\xE2\x80\x98", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x80\xA2", "\xE2\x82\xA6"],
        ["'", "'", '"', '"', '-', '-', '-', 'NGN '],
        $s
    );
    $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    return $out !== false ? $out : $s;
}

/** Word-wrap text for imagettftext within a pixel width. */
function sc_wrap_ttf(string $text, float $size, string $font, int $maxWidth): array {
    $words = preg_split('/\s+/', $text);
    $lines = [];
    $line = '';
    foreach ($words as $w) {
        $try = $line === '' ? $w : $line . ' ' . $w;
        $box = imagettfbbox($size, 0, $font, $try);
        if (abs($box[2] - $box[0]) > $maxWidth && $line !== '') {
            $lines[] = $line;
            $line = $w;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
}

/** Generate a portrait branded cover PNG. Returns true on success. */
function sc_make_ebook_cover(string $path, string $title, string $author, string $category, ?string $fontPath): bool {
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    if (!$fontPath || !is_file($fontPath)) {
        foreach (['C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'] as $f) {
            if (is_file($f)) { $fontPath = $f; break; }
        }
    }

    $W = 600; $H = 800;
    $im = imagecreatetruecolor($W, $H);
    [$r1,$g1,$b1] = [6,64,163];
    [$r2,$g2,$b2] = [5,20,55];
    for ($y = 0; $y < $H; $y++) {
        $t = $y / $H;
        $col = imagecolorallocate($im,
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t));
        imageline($im, 0, $y, $W, $y, $col);
    }
    imagefilledrectangle($im, 60, 250, 160, 258, imagecolorallocate($im, 245, 159, 34));
    $white = imagecolorallocate($im, 255, 255, 255);
    $soft  = imagecolorallocate($im, 200, 216, 239);

    if ($fontPath && is_file($fontPath)) {
        imagettftext($im, 15, 0, 60, 110, $soft, $fontPath, strtoupper($category));
        $y = 360;
        foreach (sc_wrap_ttf($title, 40, $fontPath, $W - 120) as $ln) {
            imagettftext($im, 40, 0, 60, $y, $white, $fontPath, $ln);
            $y += 64;
        }
        imagettftext($im, 16, 0, 60, $y + 24, $soft, $fontPath, 'by ' . $author);
        imagettftext($im, 18, 0, 60, $H - 60, $white, $fontPath, 'JOBMINGTON');
    }

    imagepng($im, $path, 9);
    imagedestroy($im);
    return is_file($path);
}

/**
 * Minimal, dependency-free PDF writer (base-14 Helvetica fonts, A4).
 * Enough for a clean text ebook with a colour cover page.
 */
class SC_PDF {
    private array $pages = [];
    private string $cur = '';
    private float $w = 595.28, $h = 841.89, $margin = 56.0;
    private float $y = 56.0;

    private function esc(string $s): string {
        $s = sc_pdf_text($s);
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    private function wrap(string $text, float $size): array {
        $max = $this->w - 2 * $this->margin;
        $perLine = max(1, (int) floor($max / ($size * 0.52)));
        $words = preg_split('/\s+/', trim($text));
        $lines = []; $line = '';
        foreach ($words as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if (strlen($try) > $perLine && $line !== '') { $lines[] = $line; $line = $word; }
            else $line = $try;
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: [''];
    }

    private function lineAt(float $yTop, string $text, float $size, bool $bold, array $rgb): void {
        $py = $this->h - $yTop - $size;
        $this->cur .= sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1', $size, $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $this->margin, $py, $this->esc($text));
    }

    private function ensure(float $need): void {
        if ($this->y + $need > $this->h - $this->margin) { $this->newPage(); }
    }

    public function newPage(): void {
        if ($this->cur !== '') { $this->pages[] = $this->cur; }
        $this->cur = '';
        $this->y = $this->margin;
    }

    public function coverPage(string $title, string $author): void {
        if ($this->cur !== '') { $this->pages[] = $this->cur; }
        $this->cur = '';
        $this->cur .= sprintf("%.3f %.3f %.3f rg 0 0 %.2f %.2f re f\n", 6 / 255, 64 / 255, 163 / 255, $this->w, $this->h);
        $this->cur .= sprintf("%.3f %.3f %.3f rg %.2f %.2f 120 6 re f\n", 245 / 255, 159 / 255, 34 / 255, $this->margin, $this->h - 360);
        $yTop = 400;
        foreach ($this->wrap($title, 30) as $ln) { $this->lineAt($yTop, $ln, 30, true, [255, 255, 255]); $yTop += 40; }
        $this->lineAt($yTop + 12, 'by ' . $author, 15, false, [200, 216, 239]);
        $this->lineAt($this->h - 70, 'JOBMINGTON', 16, true, [255, 255, 255]);
        $this->pages[] = $this->cur;
        $this->cur = '';
        $this->y = $this->margin;
    }

    public function heading(string $text): void {
        foreach ($this->wrap($text, 17) as $ln) { $this->ensure(24); $this->lineAt($this->y, $ln, 17, true, [6, 20, 38]); $this->y += 24; }
        $this->y += 8;
    }

    public function paragraph(string $text): void {
        foreach ($this->wrap($text, 12) as $ln) { $this->ensure(17); $this->lineAt($this->y, $ln, 12, false, [40, 50, 65]); $this->y += 16; }
        $this->y += 6;
    }

    public function gap(): void { $this->y += 8; }

    public function pageCount(): int { return count($this->pages) + ($this->cur !== '' ? 1 : 0); }

    public function save(string $path): void {
        if ($this->cur !== '') { $this->pages[] = $this->cur; $this->cur = ''; }
        $n = count($this->pages);

        $objs = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = [];
        for ($p = 0; $p < $n; $p++) { $kids[] = (5 + 2 * $p) . " 0 R"; }
        $objs[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $n >>";
        $objs[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        for ($p = 0; $p < $n; $p++) {
            $pageId = 5 + 2 * $p; $contId = $pageId + 1;
            $objs[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . sprintf('%.2f %.2f', $this->w, $this->h) . "]"
                . " /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $contId 0 R >>";
            $stream = $this->pages[$p];
            $objs[$contId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }
        ksort($objs);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $count = max(array_keys($objs)) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        file_put_contents($path, $pdf);
    }
}

/** Generate a multi-page ebook PDF. Returns page count. */
function sc_make_ebook_pdf(string $path, string $title, string $author, array $sections, string $root): int {
    $pdf = new SC_PDF();
    $pdf->coverPage($title, $author);
    foreach ($sections as $i => $sec) {
        $pdf->newPage();
        $pdf->heading(($i + 1) . '.  ' . $sec[0]);
        foreach (explode("\n", $sec[1]) as $para) {
            $para = trim($para);
            if ($para === '') { $pdf->gap(); continue; }
            $pdf->paragraph($para);
        }
    }
    $pdf->save($path);
    return $pdf->pageCount();
}
